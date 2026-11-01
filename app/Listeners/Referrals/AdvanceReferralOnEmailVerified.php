<?php

namespace App\Listeners\Referrals;

use App\Enums\Queue;
use App\Models\Referrals\Referral;
use App\Services\Referrals\ReferralLifecycle;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires the `signup_verified` trigger when a referred user confirms their
 * email. Queued so a reward failure can't break the verification redirect.
 */
class AdvanceReferralOnEmailVerified implements ShouldQueue
{
    public string $queue = Queue::Billing->value;

    public function __construct(private readonly ReferralLifecycle $lifecycle) {}

    public function handle(Verified $event): void
    {
        $referral = Referral::query()->where('referred_user_id', $event->user->getKey())->first();

        if ($referral !== null) {
            $this->lifecycle->markVerified($referral);
        }
    }
}
