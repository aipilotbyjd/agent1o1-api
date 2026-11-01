<?php

namespace App\Jobs\Referrals;

use App\Enums\Queue;
use App\Models\Referrals\Referral;
use App\Models\Workspaces\Workspace;
use App\Services\Referrals\ReferralLifecycle;
use App\Services\Referrals\ReferralPaymentData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Credits a paid Stripe charge to the referral behind the paying workspace.
 * Queued off the Stripe webhook so a referral failure (or a slow
 * fingerprint lookup) can never fail the webhook itself. The workspace's
 * own referral wins; a workspace its referred owner created later falls
 * back to the owner's referral.
 */
class ProcessReferralPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly ReferralPaymentData $payment)
    {
        $this->onQueue(Queue::Billing->value);
    }

    public function handle(ReferralLifecycle $lifecycle): void
    {
        $workspace = Workspace::query()->find($this->payment->workspaceId);

        if ($workspace === null) {
            return;
        }

        $referral = Referral::query()->where('referred_workspace_id', $workspace->id)->first()
            ?? Referral::query()->where('referred_user_id', $workspace->owner_id)->first();

        if ($referral === null) {
            return;
        }

        $lifecycle->recordPayment($referral, $this->payment);
    }
}
