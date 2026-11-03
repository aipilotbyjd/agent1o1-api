<?php

namespace App\Services\Billing;

use App\Models\Billing\CreditReservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Holds credits for work in flight, so the pre-flight balance check sees
 * what is already committed. Holding is bookkeeping, never a gate on work
 * that has already been allowed to start: a failure to hold or release is
 * reported and swallowed, and a hold the worker never released expires on
 * its own after `billing.reservations.ttl_minutes`.
 *
 * Sizes come from `billing.reservations`: an agent turn and an AI workflow
 * step each hold a typical charge for their kind of work, not a worst case,
 * so a busy but solvent workspace is never refused.
 */
class CreditReservations
{
    public function enabled(): bool
    {
        return (bool) config('billing.reservations.enabled', true);
    }

    /**
     * Credits currently held for the workspace.
     */
    public function outstanding(string $workspaceId): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        return (int) CreditReservation::query()
            ->where('workspace_id', $workspaceId)
            ->outstanding()
            ->sum('credits');
    }

    /**
     * Runs `$work` with `$credits` held for it, releasing the hold when it
     * returns or throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $work
     * @return TReturn
     */
    public function holding(string $workspaceId, string $subjectType, string $subjectId, int $credits, callable $work): mixed
    {
        $this->hold($workspaceId, $subjectType, $subjectId, $credits);

        try {
            return $work();
        } finally {
            $this->release($subjectType, $subjectId);
        }
    }

    public function hold(string $workspaceId, string $subjectType, string $subjectId, int $credits): void
    {
        if (! $this->enabled() || $credits <= 0) {
            return;
        }

        try {
            CreditReservation::query()->create([
                'workspace_id' => $workspaceId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'credits' => $credits,
                'expires_at' => now()->addMinutes(max(1, (int) config('billing.reservations.ttl_minutes', 30))),
            ]);
        } catch (UniqueConstraintViolationException) {
            CreditReservation::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->update(['credits' => $credits, 'released_at' => null, 'expires_at' => now()->addMinutes(max(1, (int) config('billing.reservations.ttl_minutes', 30)))]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function release(string $subjectType, string $subjectId): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            CreditReservation::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Deletes released and expired holds older than a day.
     */
    public function prune(): int
    {
        return CreditReservation::query()
            ->where(fn ($query) => $query->whereNotNull('released_at')->orWhere('expires_at', '<', now()))
            ->where('updated_at', '<', now()->subDay())
            ->delete();
    }

    public function agentTurnCredits(): int
    {
        return (int) config('billing.reservations.agent_turn_credits', 40);
    }

    public function aiStepCredits(): int
    {
        return (int) config('billing.reservations.ai_step_credits', 15);
    }
}
