<?php

namespace App\Services\Assistant\Runtime;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\Tools\AssistantTool;
use App\Ai\ResponseUsage;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Events\Assistant\AssistantToolActivity;
use App\Events\Assistant\AssistantTurnChanged;
use App\Events\Assistant\AssistantTurnDelta;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantTurn;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Assistant\Tools\ToolCatalog;
use App\Services\Assistant\Tools\ToolGate;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Laravel\Ai\Streaming\Events\ToolResult as ToolResultEvent;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Runs one `AssistantTurn` from start to finish inside `RunAssistantTurnJob`:
 * builds the agent, streams the model's reply to the owner over Reverb,
 * stops early if the owner cancels, and settles the turn: completed,
 * paused for approvals, failed or cancelled. A paused turn is run again by
 * the same job once every waiting call has a decision.
 */
class TurnRunner
{
    /**
     * Reply text is broadcast in chunks of about this size, not per token.
     */
    private const int DELTA_FLUSH_CHARS = 120;

    private const float DELTA_FLUSH_SECONDS = 0.25;

    private const float CANCEL_CHECK_SECONDS = 1.0;

    public function __construct(
        private readonly AssistantInstructions $instructions,
        private readonly ToolCatalog $catalog,
        private readonly AssistantModel $model,
        private readonly CreditGate $creditGate,
        private readonly CreditMeter $meter,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    public function run(AssistantTurn $turn): void
    {
        if (! $this->claim($turn)) {
            return;
        }

        $turn->refresh()->load(['session.assistant.user', 'session.assistant.workspace', 'userMessage', 'assistantMessage']);

        try {
            if ($turn->isCancelRequested()) {
                $this->finishCancelled($turn, '');

                return;
            }

            $assistant = $turn->session->assistant;
            $this->creditGate->assertCanStartRun($assistant->workspace);

            [$provider, $model] = $this->model->for($assistant);
            $tools = $this->catalog->forTurn($turn, $provider);

            $resuming = $turn->assistantMessage?->paused_state !== null ? $turn->assistantMessage : null;

            $agent = new AssistantAgent(
                $this->instructions->for($assistant, $turn->session),
                $turn->session,
                $tools,
                excludeMessageId: $resuming === null ? $turn->user_message_id : null,
                resumingMessageId: $resuming?->id,
            );

            $prompt = $resuming !== null ? $this->settleDecidedActions($turn, $tools) : (string) $turn->userMessage?->content;

            AssistantTurnChanged::dispatch($turn);

            $final = null;
            $stream = $agent->stream($prompt, provider: $provider, model: $model)
                ->then(function (StreamedAgentResponse $response) use (&$final): void {
                    $final = $response;
                });

            [$text, $cancelled] = $this->consume($turn, $stream);

            if ($cancelled || $final === null) {
                $this->finishCancelled($turn, $text);

                return;
            }

            $this->settle($turn, $final);
        } catch (Throwable $e) {
            $this->fail($turn, $e);

            if (! $e instanceof InsufficientCreditsException) {
                report($e);
            }
        }
    }

    /**
     * Moves the turn from `queued` to `running` with a conditional update,
     * so two workers (a re-dispatched job, say) can never run it twice.
     */
    private function claim(AssistantTurn $turn): bool
    {
        return AssistantTurn::query()
            ->whereKey($turn->id)
            ->where('status', AssistantTurnStatus::Queued)
            ->update(['status' => AssistantTurnStatus::Running, 'started_at' => now(), 'updated_at' => now()]) === 1;
    }

    /**
     * Pulls the stream to its end, broadcasting the reply and tool activity
     * as it goes. Returns the text written so far and whether the owner
     * cancelled, in which case the rest of the stream is never pulled.
     *
     * @return array{0: string, 1: bool}
     */
    private function consume(AssistantTurn $turn, StreamableAgentResponse $stream): array
    {
        $text = '';
        $buffer = '';
        $lastFlush = microtime(true);
        $lastCancelCheck = microtime(true);

        $flush = function () use ($turn, &$buffer, &$lastFlush): void {
            if ($buffer !== '') {
                AssistantTurnDelta::dispatch($turn, $buffer);
                $buffer = '';
            }

            $lastFlush = microtime(true);
        };

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->delta;
                $buffer .= $event->delta;

                if (mb_strlen($buffer) >= self::DELTA_FLUSH_CHARS || microtime(true) - $lastFlush >= self::DELTA_FLUSH_SECONDS) {
                    $flush();
                }
            } elseif ($event instanceof ToolCallEvent) {
                $flush();
                AssistantToolActivity::dispatch($turn, $event->toolCall->id, $event->toolCall->name, AssistantToolActivity::STARTED);
            } elseif ($event instanceof ToolResultEvent && ! ($event->preliminary ?? false)) {
                AssistantToolActivity::dispatch($turn, $event->toolResult->id, $event->toolResult->name, AssistantToolActivity::FINISHED);
            }

            if (microtime(true) - $lastCancelCheck >= self::CANCEL_CHECK_SECONDS) {
                $lastCancelCheck = microtime(true);

                if ($turn->isCancelRequested()) {
                    $flush();

                    return [$text, true];
                }
            }
        }

        $flush();

        return [$text, false];
    }

    /**
     * Runs the approved calls of a paused turn (each exactly once, through
     * the same gate that paused it) then hands the model every outcome.
     *
     * @param  list<object>  $tools
     */
    private function settleDecidedActions(AssistantTurn $turn, array $tools): Decisions
    {
        $actions = AssistantAction::query()
            ->where('assistant_turn_id', $turn->id)
            ->whereIn('tool_call_id', $turn->assistantMessage->paused_state['pending_tool_call_ids'] ?? [])
            ->get();

        $gate = ToolGate::forTurn($turn);

        foreach ($actions->where('status', AssistantActionStatus::Approved) as $action) {
            $tool = collect($tools)->first(fn (object $tool): bool => $tool instanceof AssistantTool && $tool->name() === $action->tool);

            if ($tool === null) {
                $action->forceFill(['status' => AssistantActionStatus::Failed, 'result' => 'This tool is no longer available, so the action was not run.'])->save();

                continue;
            }

            $tool->handle(new Request($action->arguments ?? [], $action->tool_call_id));
        }

        return Decisions::from($actions->each->refresh()->mapWithKeys(fn (AssistantAction $action): array => [
            $action->tool_call_id => $action->status->hasRun()
                ? Decision::approve()
                : Decision::reject($gate->rejectionText($action)),
        ])->all());
    }

    private function settle(AssistantTurn $turn, StreamedAgentResponse $response): void
    {
        $message = $this->storeReply($turn, $response);

        if ($response->hasPendingApprovals()) {
            $message->forceFill(['paused_state' => PausedStep::stateFrom($response)])->save();

            $turn->forceFill(['status' => AssistantTurnStatus::AwaitingApproval, 'assistant_message_id' => $message->id])->save();
            $this->touchSession($turn);

            AssistantTurnChanged::dispatch($turn);

            return;
        }

        $message->forceFill(['paused_state' => null])->save();

        $turn->forceFill([
            'status' => AssistantTurnStatus::Completed,
            'assistant_message_id' => $message->id,
            'usage' => $message->usage,
            'finished_at' => now(),
        ])->save();

        $this->touchSession($turn);
        $this->charge($turn);

        AssistantTurnChanged::dispatch($turn);
    }

    /**
     * Writes the reply, or, for a resumed turn, folds the rest of it into
     * the message it paused on, so one user message gets one reply.
     */
    private function storeReply(AssistantTurn $turn, StreamedAgentResponse $response): AssistantMessage
    {
        $usage = ResponseUsage::from($response, $turn->started_at ?? now());
        $calls = $response->toolCalls->map(fn (ToolCall $call): array => $call->toArray())->values()->all();
        $results = $response->toolResults->map(fn (ToolResult $result): array => $result->toArray())->values()->all();

        $existing = $turn->assistantMessage;

        if ($existing === null) {
            return $turn->session->messages()->create([
                'role' => AssistantMessageRole::Assistant,
                'content' => $response->text,
                'tool_calls' => $calls ?: null,
                'tool_results' => $results ?: null,
                'usage' => $usage,
            ]);
        }

        $existing->forceFill([
            'content' => trim(implode("\n\n", array_filter([(string) $existing->content, $response->text]))),
            'tool_calls' => $this->mergeById($existing->tool_calls ?? [], $calls) ?: null,
            'tool_results' => $this->mergeById($existing->tool_results ?? [], $results) ?: null,
            'usage' => ResponseUsage::combine($existing->usage ?? [], $usage),
        ])->save();

        return $existing;
    }

    /**
     * @param  list<array<string, mixed>>  $earlier
     * @param  list<array<string, mixed>>  $later
     * @return list<array<string, mixed>>
     */
    private function mergeById(array $earlier, array $later): array
    {
        return collect($earlier)->keyBy('id')->union(collect($later)->keyBy('id'))->values()->all();
    }

    private function finishCancelled(AssistantTurn $turn, string $text): void
    {
        $messageId = $turn->assistant_message_id;

        if (filled($text) && $messageId === null) {
            $messageId = $turn->session->messages()->create([
                'role' => AssistantMessageRole::Assistant,
                'content' => $text,
            ])->id;
        }

        $this->expirePending($turn, 'The conversation was stopped before this was decided.');

        $turn->forceFill([
            'status' => AssistantTurnStatus::Cancelled,
            'assistant_message_id' => $messageId,
            'finished_at' => now(),
        ])->save();

        $this->touchSession($turn);
        $this->charge($turn);

        AssistantTurnChanged::dispatch($turn);
    }

    private function fail(AssistantTurn $turn, Throwable $e): void
    {
        $turn->forceFill([
            'status' => AssistantTurnStatus::Failed,
            'error' => $e instanceof InsufficientCreditsException
                ? $e->getMessage()
                : 'Something went wrong while answering. Please try again.',
            'finished_at' => now(),
        ])->save();

        $this->expirePending($turn, 'The turn failed before this was decided.');

        AssistantTurnChanged::dispatch($turn);
    }

    private function expirePending(AssistantTurn $turn, string $note): void
    {
        AssistantAction::query()
            ->where('assistant_turn_id', $turn->id)
            ->where('status', AssistantActionStatus::Pending)
            ->update(['status' => AssistantActionStatus::Expired, 'decision_note' => $note, 'decided_at' => now(), 'updated_at' => now()]);
    }

    private function touchSession(AssistantTurn $turn): void
    {
        $turn->session->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Charged once per turn, on the turn id, after it settles: a paused
     * turn is charged when it finally completes, for both halves.
     */
    private function charge(AssistantTurn $turn): void
    {
        $usage = $turn->assistantMessage()->value('usage');
        $usage = is_string($usage) ? json_decode($usage, true) : $usage;

        if (! is_array($usage)) {
            return;
        }

        $this->deductCredits->execute(
            $turn->session->assistant->workspace,
            CreditTransactionType::AssistantTurn,
            $turn->id,
            $this->meter->costForAssistantTurn($usage),
            'Personal assistant',
            allowOverdraft: true,
        );
    }
}
