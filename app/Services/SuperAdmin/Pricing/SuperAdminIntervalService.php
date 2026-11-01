<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Billing\BillingSettings;
use Illuminate\Support\Facades\DB;

/**
 * The estate-wide switch per billing interval. Switching one off stops new
 * purchases on every plan at once; it never touches what was already bought.
 */
class SuperAdminIntervalService
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly PricingChangeLogger $log,
    ) {}

    /**
     * @param  array<string, bool>  $states  Interval value => on sale. Intervals not listed are left alone.
     * @return array<string, bool>
     */
    public function update(SuperAdmin $actor, array $states, string $reason): array
    {
        return DB::transaction(function () use ($actor, $states, $reason): array {
            $before = $this->settings->intervals();

            foreach ($states as $value => $enabled) {
                $this->settings->setIntervalEnabled(BillingInterval::from($value), $enabled);
            }

            $after = $this->settings->intervals();

            if ($before !== $after) {
                $this->log->record(
                    $actor,
                    'interval',
                    'updated',
                    null,
                    $before,
                    $after,
                    $reason,
                    'intervals',
                );
            }

            return $after;
        });
    }
}
