<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Billing\BillingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Changes the credit-policy and guardrail settings (free credits, trials,
 * abuse checks, the free-tier budget) and records each change in the
 * pricing change log. Settings take effect on the next decision; credits
 * already granted are not touched.
 */
class SuperAdminCreditPolicyService
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly PricingChangeLogger $log,
    ) {}

    /**
     * @param  array<string, mixed>  $values  `BillingSettings::POLICY_KEYS` key => value. Keys not listed are left alone.
     * @return array<string, mixed>
     */
    public function update(SuperAdmin $actor, array $values, string $reason): array
    {
        return DB::transaction(function () use ($actor, $values, $reason): array {
            $before = $this->settings->policies();

            foreach ($values as $key => $value) {
                $this->settings->set($key, $value);
            }

            $after = $this->settings->policies();

            if ($before !== $after) {
                $this->log->record($actor, 'credit_policy', 'updated', null, $before, $after, $reason, 'credit_policies');
            }

            return $after;
        });
    }
}
