<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\AlreadyOnPlanBehavior;
use App\Enums\Referrals\ReferralPaymentSource;
use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralRewardRule;
use BackedEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a rule create (POST) or partial update (PATCH), then checks the
 * rule *as it would be saved* (input merged over the existing rule) makes
 * sense as a whole (a plan-time reward needs a plan and a length, a trial
 * extension can only go to the referred user, and so on) so no rule that
 * can never pay out can be saved.
 */
class ReferralRewardRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::rulesFor($this->existingRule());
    }

    /**
     * Shared with the `referrals:rule` command. `$rule` null means a create,
     * where the trigger, recipient and reward type are required.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(?ReferralRewardRule $rule): array
    {
        $presence = $rule !== null ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'trigger' => [$presence, Rule::in(ReferralTrigger::ruleValues())],
            'recipient' => [$presence, Rule::enum(ReferralRecipient::class)],
            'reward_type' => [$presence, Rule::enum(ReferralRewardType::class)],
            'credits_amount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'plan_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('plans', 'id')],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'amount_percent_of_plan' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'milestone_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'hold_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'if_already_on_plan' => ['sometimes', Rule::enum(AlreadyOnPlanBehavior::class)],
            'fallback_credits' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'max_per_recipient' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'conditions' => ['sometimes', 'nullable', 'array:min_payment_cents,plan_ids,billing_intervals,payment_sources,nth_payment_min,nth_payment_max'],
            'conditions.min_payment_cents' => ['sometimes', 'integer', 'min:0'],
            'conditions.plan_ids' => ['sometimes', 'array'],
            'conditions.plan_ids.*' => ['uuid', Rule::exists('plans', 'id')],
            'conditions.billing_intervals' => ['sometimes', 'array'],
            'conditions.billing_intervals.*' => [Rule::enum(BillingInterval::class)],
            'conditions.payment_sources' => ['sometimes', 'array'],
            'conditions.payment_sources.*' => [Rule::enum(ReferralPaymentSource::class)],
            'conditions.nth_payment_min' => ['sometimes', 'integer', 'min:1'],
            'conditions.nth_payment_max' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (self::consistencyErrors($this->existingRule(), $this->validated()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * Checks the rule as it would be saved (`$input` merged over `$rule`)
     * makes sense as a whole. Shared with the `referrals:rule` command.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string> Field => message.
     */
    public static function consistencyErrors(?ReferralRewardRule $rule, array $input): array
    {
        $merged = [...($rule?->attributesToArray() ?? []), ...$input];
        $value = fn (string $key): mixed => ($merged[$key] ?? null) instanceof BackedEnum ? $merged[$key]->value : ($merged[$key] ?? null);
        $missing = fn (string $key): bool => ($merged[$key] ?? null) === null;

        $type = ReferralRewardType::tryFrom((string) $value('reward_type'));
        $trigger = ReferralTrigger::tryFrom((string) $value('trigger'));
        $recipient = ReferralRecipient::tryFrom((string) $value('recipient'));
        $errors = [];

        if ($type === ReferralRewardType::Credits && $missing('credits_amount')) {
            $errors['credits_amount'] = 'A credits reward needs an amount.';
        }

        if ($type === ReferralRewardType::PlanTime && ($missing('plan_id') || $missing('duration_days'))) {
            $errors['plan_id'] = 'A plan-time reward needs a plan and a number of days.';
        }

        if ($type === ReferralRewardType::StripeBalanceCredit && $missing('amount_cents') && ($missing('amount_percent_of_plan') || $missing('plan_id'))) {
            $errors['amount_cents'] = 'An invoice credit needs a fixed amount, or a percentage of a plan\'s monthly price.';
        }

        if ($type === ReferralRewardType::TrialExtension && $missing('trial_days')) {
            $errors['trial_days'] = 'A trial extension needs a number of days.';
        }

        if ($type === ReferralRewardType::TrialExtension && $recipient !== ReferralRecipient::Referee) {
            $errors['recipient'] = 'Only the referred user can receive a trial extension.';
        }

        if ($trigger === ReferralTrigger::Milestone) {
            if ($missing('milestone_count')) {
                $errors['milestone_count'] = 'A milestone rule needs the number of converted referrals it pays out at.';
            }

            if ($recipient !== ReferralRecipient::Referrer) {
                $errors['recipient'] = 'Milestone rewards go to the referrer.';
            }
        }

        return $errors;
    }

    private function existingRule(): ?ReferralRewardRule
    {
        $rule = $this->route('rule');

        return $rule instanceof ReferralRewardRule ? $rule : null;
    }
}
