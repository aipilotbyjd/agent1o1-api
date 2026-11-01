<?php

namespace App\Services\Agents;

use App\Ai\Tools\InvokeAgentTool;
use App\Events\Agents\AgentTurnChanged;
use App\Events\Agents\AgentTurnDelta;
use App\Events\Agents\AgentTurnToolActivity;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

/**
 * Delivers one chat turn (a new message, or a paused turn resumed after
 * approvals) to everyone watching the conversation over Reverb, from inside
 * a queue job (`RunAgentTurnJob`, `ResumeAgentTurnJob`). What goes out, on
 * the conversation's private channel:
 *
 * - `turn.changed`: the turn started (`running`), paused (`awaiting_approval`),
 *   finished (`completed`) or failed. Anything but `running` is the cue to
 *   fetch the stored message.
 * - `turn.delta`: a chunk of assistant text; concatenate in order received.
 * - `turn.tool`: a tool call started or finished.
 *
 * The persisted transcript is written by `AgentRunner` regardless of who is
 * listening: a dropped socket loses the live view, never the conversation.
 */
class AgentTurnBroadcaster
{
    /**
     * Reply text is broadcast in chunks of about this size, not per token.
     */
    private const int DELTA_FLUSH_CHARS = 120;

    private const float DELTA_FLUSH_SECONDS = 0.25;

    public function __construct(private readonly AgentRunner $runner) {}

    /**
     * Pulls the turn's provider stream to its end, broadcasting as it goes.
     * Failure is owned here, not by `AgentRunner`: nothing runs until the
     * stream is iterated, so an exception surfaces in this loop and the
     * turn's `Run` must be failed from it: see `AgentRunner::failTurn()`.
     */
    public function broadcast(StreamedTurn $turn): void
    {
        $session = $turn->run->runnable;

        if (! $session instanceof AgentSession) {
            $this->fail($turn->run, new InvalidArgumentException("Run [{$turn->run->id}] is not an agent conversation turn."));

            return;
        }

        $buffer = '';
        $lastFlush = microtime(true);

        $flush = function () use ($session, $turn, &$buffer, &$lastFlush): void {
            if ($buffer !== '') {
                AgentTurnDelta::dispatch($session, $turn->run, $buffer);
                $buffer = '';
            }

            $lastFlush = microtime(true);
        };

        AgentTurnChanged::dispatch($session, $turn->run);

        try {
            foreach ($turn->response as $event) {
                if ($event instanceof TextDelta) {
                    $buffer .= $event->delta;

                    if (mb_strlen($buffer) >= self::DELTA_FLUSH_CHARS || microtime(true) - $lastFlush >= self::DELTA_FLUSH_SECONDS) {
                        $flush();
                    }
                } elseif ($event instanceof ToolCall) {
                    $flush();

                    AgentTurnToolActivity::dispatch(
                        $session,
                        $turn->run,
                        $event->toolCall->id,
                        $event->toolCall->name,
                        AgentTurnToolActivity::STARTED,
                        arguments: $this->smallEnough($event->toolCall->arguments),
                    );
                } elseif ($event instanceof ToolResult) {
                    $flush();

                    AgentTurnToolActivity::dispatch(
                        $session,
                        $turn->run,
                        $event->toolResult->id,
                        $event->toolResult->name,
                        AgentTurnToolActivity::FINISHED,
                        successful: $event->successful,
                        denied: (bool) $event->denied,
                        subagentTaskId: $this->subagentTaskId($event),
                        output: Str::limit((string) ($event->error ?? $event->toolResult->result), AgentTurnToolActivity::MAX_OUTPUT_CHARS),
                    );
                }
            }

            $flush();

            // The `then()` callback that persists the reply has run by now,
            // so the run's output holds the message id.
            AgentTurnChanged::dispatch($session, $turn->run->fresh());
        } catch (Throwable $e) {
            $flush();

            $this->fail($turn->run, $e);
        }
    }

    /**
     * Fails a turn and tells the conversation. Safe to call on a run that
     * is already failed (`AgentRunner::failTurn()` is idempotent), which is
     * how a job reports a turn that never got as far as streaming. The chat
     * gets a message that is safe to show; the raw failure is on the run
     * and goes to the exception handler.
     */
    public function fail(Run $run, Throwable $e): void
    {
        $this->runner->failTurn($run, $e);

        if (($session = $run->runnable) instanceof AgentSession) {
            AgentTurnChanged::dispatch($session, $run->fresh(), 'Something went wrong while answering. Please try again.');
        }

        report($e);
    }

    /**
     * Tool arguments, or null when they would make the event too big to send.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function smallEnough(array $arguments): ?array
    {
        return strlen((string) json_encode($arguments)) <= AgentTurnToolActivity::MAX_ARGUMENTS_BYTES ? $arguments : null;
    }

    /**
     * The task id of a subagent this result started, if it is one.
     */
    private function subagentTaskId(ToolResult $event): ?string
    {
        if ($event->toolResult->name !== InvokeAgentTool::NAME) {
            return null;
        }

        $result = json_decode((string) $event->toolResult->result, true);

        return is_array($result) && isset($result['task_id']) ? (string) $result['task_id'] : null;
    }
}
