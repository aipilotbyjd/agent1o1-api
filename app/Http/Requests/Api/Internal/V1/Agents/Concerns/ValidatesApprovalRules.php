<?php

namespace App\Http\Requests\Api\Internal\V1\Agents\Concerns;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionVerdict;
use App\Services\Agents\Approvals\ConditionEvaluator;
use Illuminate\Validation\Rule;

/**
 * The shapes `ActionGate` reads: a tool's `approval_policy` and the
 * workspace's `guardrails`. Validated at the edge so a malformed rule is
 * refused when saved rather than silently ignored when an agent acts.
 */
trait ValidatesApprovalRules
{
    /**
     * @return array<string, mixed>
     */
    protected function approvalPolicyRules(string $prefix = 'approval_policy'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.mode" => ['nullable', Rule::in([...ActionVerdict::ruleValues(), 'inherit'])],
            ...$this->conditionRules("{$prefix}.conditions", withThen: true),
            "{$prefix}.rate_limit" => ['nullable', 'array'],
            "{$prefix}.rate_limit.max" => ["required_with:{$prefix}.rate_limit", 'integer', 'min:1', 'max:100000'],
            "{$prefix}.rate_limit.per" => ['nullable', Rule::in(['minute', 'hour', 'day'])],
            "{$prefix}.rate_limit.then" => ['nullable', Rule::in([ActionVerdict::Ask->value, ActionVerdict::Deny->value])],
            "{$prefix}.approvers" => ['nullable', 'array', 'max:50'],
            "{$prefix}.approvers.*" => ['string', 'regex:/^(user:[0-9a-fA-F-]{36}|role:(owner|admin|editor|member))$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function guardrailRules(string $prefix = 'guardrails'): array
    {
        return [
            $prefix => ['nullable', 'array', 'max:50'],
            "{$prefix}.*.name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.*.tools" => ['nullable', 'array'],
            "{$prefix}.*.tools.*" => ['string', 'max:255'],
            "{$prefix}.*.effects" => ['nullable', 'array'],
            "{$prefix}.*.effects.*" => [Rule::enum(ActionEffect::class)],
            ...$this->conditionRules("{$prefix}.*.conditions", withThen: false),
            "{$prefix}.*.then" => ['required', Rule::in(ActionVerdict::ruleValues())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function conditionRules(string $prefix, bool $withThen): array
    {
        return array_filter([
            $prefix => ['nullable', 'array', 'max:20'],
            "{$prefix}.*.field" => ['required', 'string', 'max:255'],
            "{$prefix}.*.op" => ['required', Rule::in(ConditionEvaluator::OPERATORS)],
            "{$prefix}.*.value" => ['nullable'],
            "{$prefix}.*.label" => ['nullable', 'string', 'max:255'],
            "{$prefix}.*.then" => $withThen ? ['required', Rule::in(ActionVerdict::ruleValues())] : null,
        ]);
    }
}
