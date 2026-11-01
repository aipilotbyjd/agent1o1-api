<?php

namespace App\Services\Assistant\Runtime;

use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Events\Assistant\AssistantTurnChanged;
use App\Exceptions\RunStateException;
use App\Jobs\Assistant\RunAssistantTurnJob;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantQueuedInput;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Services\Billing\CreditGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Everything the owner does to a conversation: send a message (it runs now,
 * or waits its turn if one is running), stop the running turn, and decide
 * on calls a paused turn is waiting for. The turn itself runs in
 * `RunAssistantTurnJob`.
 */
class AssistantLoop
{
    private const int TITLE_LENGTH = 60;

    public function __construct(private readonly CreditGate $creditGate) {}

    /**
     * A message while a turn is running is queued and becomes the next turn
     * when it ends. A message while one is waiting on approvals means the
     * owner moved on: the waiting calls are expired and the turn closed.
     */
    public function send(AssistantSession $session, string $content): AssistantTurn|AssistantQueuedInput
    {
        $this->creditGate->assertCanStartRun($session->assistant->workspace);

        $result = Cache::lock("assistant-session:{$session->id}:turn", 10)->block(5, function () use ($session, $content): AssistantTurn|AssistantQueuedInput {
            if ($this->inFlightTurn($session) !== null) {
                return $session->queuedInputs()->create(['content' => $content]);
            }

            $this->abandonPausedTurns($session);

            return $this->openTurn($session, $content);
        });

        return $result instanceof AssistantTurn ? $this->dispatch($result) : $result;
    }

    /**
     * Picks up the oldest queued message once the session is free.
     */
    public function startNextQueued(AssistantSession $session): ?AssistantTurn
    {
        $turn = Cache::lock("assistant-session:{$session->id}:turn", 10)->block(5, function () use ($session): ?AssistantTurn {
            if ($this->inFlightTurn($session) !== null || $this->pausedTurn($session) !== null) {
                return null;
            }

            $next = $session->queuedInputs()->whereNull('consumed_at')->oldest()->oldest('id')->first();

            if ($next === null) {
                return null;
            }

            $next->forceFill(['consumed_at' => now()])->save();

            return $this->openTurn($session, $next->content);
        });

        return $turn !== null ? $this->dispatch($turn) : null;
    }

    /**
     * Stops the turn: a queued one is cancelled at once, a running one stops
     * at its next check (within about a second), and a paused one is
     * closed with its waiting calls expired. Queued follow-up messages are
     * dropped too: stopping means stop.
     */
    public function cancel(AssistantTurn $turn): AssistantTurn
    {
        $turn->session->queuedInputs()->whereNull('consumed_at')->delete();

        $turn->forceFill(['cancel_requested_at' => now()])->save();

        $closed = AssistantTurn::query()
            ->whereKey($turn->id)
            ->whereIn('status', [AssistantTurnStatus::Queued, AssistantTurnStatus::AwaitingApproval])
            ->update(['status' => AssistantTurnStatus::Cancelled, 'finished_at' => now(), 'updated_at' => now()]);

        if ($closed === 1) {
            $this->expireWaiting($turn, 'The conversation was stopped before this was decided.');
            AssistantTurnChanged::dispatch($turn->refresh());
        }

        return $turn->refresh();
    }

    /**
     * Records the owner's decisions; once nothing is left undecided the turn
     * goes back on the queue to run the approved calls and carry on.
     *
     * @param  array<string, array{approve: bool, note?: string|null}>  $decisions  tool_call_id => decision
     */
    public function decide(AssistantTurn $turn, array $decisions): AssistantTurn
    {
        if ($turn->status !== AssistantTurnStatus::AwaitingApproval) {
            throw RunStateException::notAwaitingApproval();
        }

        DB::transaction(function () use ($turn, $decisions): void {
            $pending = $turn->actions()->where('status', AssistantActionStatus::Pending)->lockForUpdate()->get()->keyBy('tool_call_id');

            $unknown = array_diff(array_keys($decisions), $pending->keys()->all());

            if ($unknown !== []) {
                throw ValidationException::withMessages(['decisions' => 'Some of these actions are not waiting for a decision.']);
            }

            foreach ($decisions as $toolCallId => $decision) {
                $pending[$toolCallId]->forceFill([
                    'status' => $decision['approve'] ? AssistantActionStatus::Approved : AssistantActionStatus::Rejected,
                    'decision_note' => $decision['note'] ?? null,
                    'decided_at' => now(),
                ])->save();
            }
        });

        return $this->resumeIfDecided($turn);
    }

    /**
     * Requeues a paused turn once none of its calls are still pending.
     */
    public function resumeIfDecided(AssistantTurn $turn): AssistantTurn
    {
        if ($turn->actions()->where('status', AssistantActionStatus::Pending)->exists()) {
            return $turn->refresh();
        }

        $requeued = AssistantTurn::query()
            ->whereKey($turn->id)
            ->where('status', AssistantTurnStatus::AwaitingApproval)
            ->update(['status' => AssistantTurnStatus::Queued, 'updated_at' => now()]);

        if ($requeued === 1) {
            RunAssistantTurnJob::dispatch($turn->refresh());
        }

        return $turn->refresh();
    }

    /**
     * Dispatched after the session lock is released: a synchronous queue
     * runs the job (which takes the same lock for the next queued message)
     * right here.
     */
    private function dispatch(AssistantTurn $turn): AssistantTurn
    {
        RunAssistantTurnJob::dispatch($turn);

        return $turn->refresh();
    }

    /**
     * The user message and its queued turn: the session is now held.
     */
    private function openTurn(AssistantSession $session, string $content): AssistantTurn
    {
        $userMessage = $session->messages()->create([
            'role' => AssistantMessageRole::User,
            'content' => $content,
        ]);

        $turn = $session->turns()->create([
            'user_message_id' => $userMessage->id,
            'status' => AssistantTurnStatus::Queued,
        ]);

        $session->forceFill([
            'title' => $session->title ?? Str::limit(Str::squish($content), self::TITLE_LENGTH),
            'last_activity_at' => now(),
        ])->save();

        return $turn;
    }

    /**
     * A running turn older than the stale limit came from a dead worker: it
     * no longer holds the session.
     */
    private function inFlightTurn(AssistantSession $session): ?AssistantTurn
    {
        return $session->turns()
            ->whereIn('status', AssistantTurnStatus::inFlightValues())
            ->where('created_at', '>', now()->subMinutes((int) config('assistant.runtime.turn_stale_after_minutes')))
            ->first();
    }

    private function pausedTurn(AssistantSession $session): ?AssistantTurn
    {
        return $session->turns()->where('status', AssistantTurnStatus::AwaitingApproval)->first();
    }

    private function abandonPausedTurns(AssistantSession $session): void
    {
        $session->turns()->where('status', AssistantTurnStatus::AwaitingApproval)->get()
            ->each(function (AssistantTurn $turn): void {
                $this->expireWaiting($turn, 'The person moved on before deciding.');

                $turn->forceFill(['status' => AssistantTurnStatus::Completed, 'finished_at' => now()])->save();
                $turn->assistantMessage?->forceFill(['paused_state' => null])->save();

                AssistantTurnChanged::dispatch($turn);
            });
    }

    private function expireWaiting(AssistantTurn $turn, string $note): void
    {
        AssistantAction::query()
            ->where('assistant_turn_id', $turn->id)
            ->where('status', AssistantActionStatus::Pending)
            ->update(['status' => AssistantActionStatus::Expired, 'decision_note' => $note, 'decided_at' => now(), 'updated_at' => now()]);
    }
}
