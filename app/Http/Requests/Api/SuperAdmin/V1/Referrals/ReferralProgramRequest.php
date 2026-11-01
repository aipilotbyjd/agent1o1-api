<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Enums\Referrals\ReferralActivationEvent;
use App\Enums\Referrals\ReferralApprovalMode;
use App\Models\Referrals\ReferralProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a program create (POST) or partial update (PATCH). Every
 * setting is bounded, so a typo can't hand out a 10-year hold or an
 * unlimited window by accident.
 */
class ReferralProgramRequest extends FormRequest
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
        $program = $this->route('program');

        return self::rulesFor($program instanceof ReferralProgram ? $program : null);
    }

    /**
     * Shared with the `referrals:program` command. `$program` null means a
     * create, where `name` is required.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(?ReferralProgram $program): array
    {
        $presence = $program !== null ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('referral_programs', 'slug')->ignore($program?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'attribution_window_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'claim_window_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'require_verified_email' => ['sometimes', 'boolean'],
            'activation_event' => ['sometimes', Rule::enum(ReferralActivationEvent::class)],
            'activation_min_count' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'activation_window_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'default_hold_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'approval_mode' => ['sometimes', Rule::enum(ReferralApprovalMode::class)],
            'revoke_on_partial_refund' => ['sometimes', 'boolean'],
            'referrer_monthly_credit_cap' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'referrer_max_stacked_plan_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:36500'],
            'referrer_max_referrals_per_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'referrer_min_account_age_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'referrer_eligible_plan_ids' => ['sometimes', 'nullable', 'array'],
            'referrer_eligible_plan_ids.*' => ['uuid', Rule::exists('plans', 'id')],
            'fraud_checks' => ['sometimes', 'nullable', 'array:'.implode(',', array_keys(ReferralProgram::DEFAULT_FRAUD_CHECKS))],
            'fraud_checks.shared_workspace' => ['sometimes', 'boolean'],
            'fraud_checks.same_email_domain' => ['sometimes', 'boolean'],
            'fraud_checks.disposable_email' => ['sometimes', 'boolean'],
            'fraud_checks.card_fingerprint' => ['sometimes', 'boolean'],
            'fraud_checks.ip_velocity' => ['sometimes', 'array:enabled,max,hours'],
            'fraud_checks.ip_velocity.enabled' => ['sometimes', 'boolean'],
            'fraud_checks.ip_velocity.max' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'fraud_checks.ip_velocity.hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'fraud_checks.velocity_alert' => ['sometimes', 'array:enabled,max_per_hour'],
            'fraud_checks.velocity_alert.enabled' => ['sometimes', 'boolean'],
            'fraud_checks.velocity_alert.max_per_hour' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
