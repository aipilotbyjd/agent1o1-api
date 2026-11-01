<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Feature;
use App\Enums\Billing\PlanLimit;
use App\Models\Billing\Plan;

class PreviewPlanRequest extends PlanPayloadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $intervals = implode(',', array_map(fn (BillingInterval $interval): string => $interval->value, BillingInterval::cases()));
        $limitKeys = [...array_map(fn (PlanLimit $limit): string => $limit->value, PlanLimit::cases()), 'min_poll_interval_minutes', ...Plan::USAGE_LIMIT_KEYS];

        return [
            'credits_monthly' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'limits' => ['sometimes', 'array:'.implode(',', $limitKeys)],
            'limits.*' => ['nullable', 'numeric', 'min:-1', 'max:100000000'],
            ...collect($limitKeys)->diff(Plan::DOLLAR_LIMIT_KEYS)->mapWithKeys(fn (string $key): array => ["limits.{$key}" => ['nullable', 'integer']])->all(),
            ...collect(Plan::DOLLAR_LIMIT_KEYS)->mapWithKeys(fn (string $key): array => ["limits.{$key}" => ['nullable', 'numeric', 'min:-1', 'max:1000000']])->all(),
            'limits.credit_rollover_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'features' => ['sometimes', 'array:'.implode(',', array_map(fn (Feature $feature): string => $feature->value, Feature::cases()))],
            'features.*' => ['boolean'],
            'prices' => ['sometimes', 'array:'.$intervals],
            'prices.*' => ['integer', 'min:0', 'max:100000000'],
        ];
    }
}
