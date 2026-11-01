<?php

namespace App\Jobs\Referrals;

use App\Enums\Queue;
use App\Services\Referrals\ReferralLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Withdraws the referral rewards a refunded or disputed payment earned.
 */
class ProcessReferralRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $paymentIntentId,
        public readonly bool $fullyRefunded,
        public readonly string $reason,
    ) {
        $this->onQueue(Queue::Billing->value);
    }

    public function handle(ReferralLifecycle $lifecycle): void
    {
        $lifecycle->recordRefund($this->paymentIntentId, $this->fullyRefunded, $this->reason);
    }
}
