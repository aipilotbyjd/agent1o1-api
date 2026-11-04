<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Feature;
use App\Enums\Billing\PlanLimit;
use App\Models\Billing\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The attributes a plan's create and update requests share. Limit and
 * feature keys are checked against the enums, so a typo is a 422 rather than
 * a dead key that silently does nothing.
 */
abstract class PlanPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(string $presence): array
    {
        $limitKeys = [...array_map(fn (PlanLimit $limit): string => $limit->value, PlanLimit::cases()), 'min_poll_interval_minutes', ...Plan::USAGE_LIMIT_KEYS];
        $featureKeys = array_map(fn (Feature $feature): string => $feature->value, Feature::cases());

        return [
            'name' => [$presence, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'credits_monthly' => [$presence, 'integer', 'min:0', 'max:100000000'],
            'tier' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limits' => ['sometimes', 'array:'.implode(',', $limitKeys)],
            'limits.*' => ['numeric', 'min:-1', 'max:100000000'],
            ...collect($limitKeys)->diff(Plan::DOLLAR_LIMIT_KEYS)->mapWithKeys(fn (string $key): array => ["limits.{$key}" => ['integer']])->all(),
            ...collect(Plan::DOLLAR_LIMIT_KEYS)->mapWithKeys(fn (string $key): array => ["limits.{$key}" => ['numeric', 'min:-1', 'max:1000000']])->all(),
            'limits.credit_rollover_percent' => ['integer', 'min:0', 'max:100'],
            'features' => ['sometimes', 'array:'.implode(',', $featureKeys)],
            'features.*' => ['boolean'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'badge' => ['nullable', 'string', 'max:40'],
            'is_public' => ['sometimes', 'boolean'],
            'enabled_intervals' => ['sometimes', 'array'],
            'enabled_intervals.*' => ['distinct', Rule::enum(BillingInterval::class)],
        ];
    }

    /**
     * The validated data with the features map reduced to what is switched
     * on, the shape the seeded plans use.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (isset($data['features'])) {
            $data['features'] = array_filter($data['features']);
        }

        if (isset($data['enabled_intervals'])) {
            $data['enabled_intervals'] = array_values($data['enabled_intervals']);
        }

        return $data;
    }
}
