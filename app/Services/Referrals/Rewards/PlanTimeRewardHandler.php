<?php

namespace App\Services\Referrals\Rewards;

use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\PlanGrantSource;
use App\Enums\Billing\PlanGrantStatus;
use App\Enums\Referrals\ReferralRecipient;
use App\Models\Billing\PlanGrant;
use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Carbon;

/**
 * Free plan time, held as one referral `PlanGrant` per workspace and plan:
 * a second reward extends the live grant's `expires_at` instead of adding a
 * row, so earned time stacks into a single end date. A referrer's stack is
 * capped by the program's `referrer_max_stacked_plan_days` (frozen into the
 * reward's snapshot); the reward records the days it actually added.
 */
class PlanTimeRewardHandler implements RewardHandler
{
    public function __construct(
        private readonly ActivatePlanGrantAction $activateGrant,
        private readonly RevokePlanGrantAction $revokeGrant,
    ) {}

    public function grant(ReferralReward $reward): void
    {
        if ($reward->workspace_id === null || $reward->plan_id === null || ($reward->duration_days ?? 0) <= 0) {
            return;
        }

        $workspace = Workspace::query()->whereKey($reward->workspace_id)->lockForUpdate()->first();

        if ($workspace === null) {
            return;
        }

        $existing = $workspace->planGrants()
            ->where('source', PlanGrantSource::Referral)
            ->where('plan_id', $reward->plan_id)
            ->active()
            ->lockForUpdate()
            ->first();

        $currentEnd = $existing?->expires_at ?? now();
        $days = $this->cappedDays($reward, $currentEnd);

        if ($days <= 0) {
            $reward->duration_days = 0;
            $reward->notes = trim(($reward->notes ?? '').' Not granted: the stacked plan-time cap is already reached.');

            return;
        }

        $expiresAt = $currentEnd->copy()->addDays($days);

        if ($existing !== null) {
            $existing->update(['expires_at' => $expiresAt, 'expiry_notified_at' => null]);
            $grant = $existing;
        } else {
            $grant = PlanGrant::query()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $reward->plan_id,
                'source' => PlanGrantSource::Referral,
                'status' => PlanGrantStatus::Pending,
                'price_cents' => 0,
                'expires_at' => $expiresAt,
            ]);

            $this->activateGrant->execute($grant);
        }

        $reward->plan_grant_id = $grant->id;
        $reward->duration_days = $days;
    }

    /**
     * Shortens the grant by the days this reward added. Once that puts the
     * end in the past there is nothing left of it, so the grant is revoked
     * (which also re-sizes the usage period).
     */
    public function revoke(ReferralReward $reward): void
    {
        $grant = $reward->planGrant;

        if ($grant === null || ($reward->duration_days ?? 0) <= 0 || $grant->status !== PlanGrantStatus::Active) {
            return;
        }

        $expiresAt = ($grant->expires_at ?? now())->copy()->subDays($reward->duration_days);

        if ($expiresAt->isPast()) {
            $this->revokeGrant->execute($grant);

            return;
        }

        $grant->update(['expires_at' => $expiresAt]);
    }

    private function cappedDays(ReferralReward $reward, Carbon $currentEnd): int
    {
        $days = (int) $reward->duration_days;
        $cap = $reward->recipient_role === ReferralRecipient::Referrer
            ? $reward->cap('referrer_max_stacked_plan_days')
            : null;

        if ($cap === null) {
            return $days;
        }

        $remaining = $currentEnd->isFuture() ? (int) ceil(now()->diffInDays($currentEnd)) : 0;

        return max(0, min($days, $cap - $remaining));
    }
}
