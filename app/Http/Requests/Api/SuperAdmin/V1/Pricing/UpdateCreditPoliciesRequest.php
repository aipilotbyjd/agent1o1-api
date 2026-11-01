<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Support\Arr;

class UpdateCreditPoliciesRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'policies' => ['required', 'array:free_credits,trials,abuse,guardrails', 'min:1'],
            'policies.free_credits' => ['sometimes', 'array:one_per_owner,require_verified_email'],
            'policies.free_credits.one_per_owner' => ['sometimes', 'boolean'],
            'policies.free_credits.require_verified_email' => ['sometimes', 'boolean'],
            'policies.trials' => ['sometimes', 'array:one_per_user,one_per_card,block_prepaid_cards'],
            'policies.trials.one_per_user' => ['sometimes', 'boolean'],
            'policies.trials.one_per_card' => ['sometimes', 'boolean'],
            'policies.trials.block_prepaid_cards' => ['sometimes', 'boolean'],
            'policies.abuse' => ['sometimes', 'array:enforcement,max_free_claims_per_network_per_day,block_disposable_email'],
            'policies.abuse.enforcement' => ['sometimes', 'string', 'in:enforce,shadow,off'],
            'policies.abuse.max_free_claims_per_network_per_day' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'policies.abuse.block_disposable_email' => ['sometimes', 'boolean'],
            'policies.guardrails' => ['sometimes', 'array:enabled,free_tier_daily_budget_usd'],
            'policies.guardrails.enabled' => ['sometimes', 'boolean'],
            'policies.guardrails.free_tier_daily_budget_usd' => ['sometimes', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * The validated settings as `BillingSettings::POLICY_KEYS` key => value,
     * with booleans and numbers cast to their real types.
     *
     * @return array<string, mixed>
     */
    public function policies(): array
    {
        return collect(Arr::dot($this->validated('policies')))
            ->map(fn (mixed $value, string $key): mixed => match (true) {
                $key === 'abuse.enforcement' => (string) $value,
                $key === 'abuse.max_free_claims_per_network_per_day' => (int) $value,
                $key === 'guardrails.free_tier_daily_budget_usd' => (float) $value,
                default => (bool) $value,
            })
            ->all();
    }
}
