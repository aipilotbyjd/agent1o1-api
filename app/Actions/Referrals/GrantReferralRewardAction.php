<?php

namespace App\Actions\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Models\Referrals\ReferralReward;
use App\Models\SuperAdmin\SuperAdmin;
use App\Notifications\Referrals\ReferralRewardGrantedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Applies a reward through its type's handler and marks it granted.
 * Idempotent: a reward already granted or revoked is left alone, and the
 * row lock stops `referrals:grant-pending` and an admin's "grant now" from
 * applying the same reward twice.
 */
class GrantReferralRewardAction
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function execute(ReferralReward $reward, ?SuperAdmin $grantedBy = null): bool
    {
        $granted = DB::transaction(function () use ($reward, $grantedBy): ?ReferralReward {
            $locked = ReferralReward::query()->whereKey($reward->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->status->isOpen()) {
                return null;
            }

            app($locked->reward_type->handler())->grant($locked);

            $locked->forceFill([
                'status' => ReferralRewardStatus::Granted,
                'granted_at' => now(),
                'granted_by' => $grantedBy?->id ?? $locked->granted_by,
            ])->save();

            return $locked;
        });

        if ($granted === null) {
            return false;
        }

        $reward->setRawAttributes($granted->getAttributes(), true);

        $this->notifyRecipient($granted);

        return true;
    }

    private function notifyRecipient(ReferralReward $reward): void
    {
        $workspace = $reward->workspace;
        $recipient = $reward->recipient;

        // A capped-to-nothing reward has nothing worth announcing.
        if ($workspace === null || $recipient === null || ! $this->givesSomething($reward)) {
            return;
        }

        $this->notifications->dispatch([$recipient], new ReferralRewardGrantedNotification($workspace, $reward));
    }

    private function givesSomething(ReferralReward $reward): bool
    {
        return ($reward->credits ?? 0) > 0
            || ($reward->duration_days ?? 0) > 0
            || ($reward->amount_cents ?? 0) > 0
            || ($reward->trial_days ?? 0) > 0;
    }
}
