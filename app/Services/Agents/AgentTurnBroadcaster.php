<?php

namespace App\Services\Agents;

use App\Ai\Support\ToolCallMarkup;
use App\Ai\Tools\Workspace\InvokeAgentTool;
use App\Events\Agents\AgentTurnChanged;
use App\Events\Agents\AgentTurnDelta;
use App\Events\Agents\AgentTurnReasoning;
use App\Events\Agents\AgentTurnToolActivity;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\StreamStart;
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
 * - `turn.reasoning`: a chunk of the model's reasoning, to show while it
 *   thinks. The finished text is stored on the reply by `AgentRunner`.
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

    /**
     * Longest text value kept when a tool call's arguments are cut down.
     */
    private const int ARGUMENT_PREVIEW_CHARS = 200;

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
        $reasoning = '';
        $lastFlush = microtime(true);
        $markup = new ToolCallMarkup;
        $sentText = '';
        $needsBreak = false;

        $flush = function (bool $final = false) use ($session, $turn, &$buffer, &$reasoning, &$lastFlush, $markup): void {
            if ($final) {
                $buffer .= $markup->finish();
            }

            // Reasoning always precedes the text it led to.
            if ($reasoning !== '') {
                AgentTurnReasoning::dispatch($session, $turn->run, $reasoning);
                $reasoning = '';
            }

            if ($buffer !== '') {
                AgentTurnDelta::dispatch($session, $turn->run, $buffer);
                $buffer = '';
            }

            $lastFlush = microtime(true);
        };

        AgentTurnChanged::dispatch($session, $turn->run);

        try {
            foreach ($turn->response as $event) {
                if ($event instanceof StreamStart) {
                    $needsBreak = true;
                } elseif ($event instanceof ReasoningDelta) {
                    $reasoning .= $event->delta;

                    if (mb_strlen($reasoning) >= self::DELTA_FLUSH_CHARS || microtime(true) - $lastFlush >= self::DELTA_FLUSH_SECONDS) {
                        $flush();
                    }
                } elseif ($event instanceof TextDelta) {
                    $visible = $markup->push($event->delta);

                    if ($reasoning !== '') {
                        $flush();
                    }

                    // Text from a new step, or after a tool ran, is a new
                    // paragraph: the stored reply joins steps the same way.
                    if ($visible !== '' && $needsBreak) {
                        $needsBreak = false;

                        if ($sentText !== '' && ! preg_match('/\s$/', $sentText) && ! preg_match('/^\s/', $visible)) {
                            $visible = "\n\n".$visible;
                        }
                    }

                    $buffer .= $visible;
                    $sentText = $visible !== '' ? $visible : $sentText;

                    if (mb_strlen($buffer) >= self::DELTA_FLUSH_CHARS || microtime(true) - $lastFlush >= self::DELTA_FLUSH_SECONDS) {
                        $flush();
                    }
                } elseif ($event instanceof ToolCall) {
                    $needsBreak = true;
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
                    $needsBreak = true;
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

            $flush(final: true);

            // The `then()` callback that persists the reply has run by now,
            // so the run's output holds the message id.
            AgentTurnChanged::dispatch($session, $turn->run->fresh());
        } catch (Throwable $e) {
            $flush(final: true);

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
     * Tool arguments for the live event. When they would make it too big to
     * send, long text values (a document's `content`, say) are cut short so
     * the short ones that label the call (`filename`) still get through; null
     * only when even that is too big.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function smallEnough(array $arguments): ?array
    {
        if ($this->fits($arguments)) {
            return $arguments;
        }

        $shortened = array_map(
            fn (mixed $value): mixed => is_string($value) ? Str::limit($value, self::ARGUMENT_PREVIEW_CHARS) : $value,
            $arguments,
        );

        return $this->fits($shortened) ? $shortened : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function fits(array $arguments): bool
    {
        return strlen((string) json_encode($arguments)) <= AgentTurnToolActivity::MAX_ARGUMENTS_BYTES;
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
