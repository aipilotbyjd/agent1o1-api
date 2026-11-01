<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Referrals;

use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;
use App\Enums\Referrals\ReferralTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateReferralRequest extends FormRequest
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
        return [
            'trigger' => ['required', Rule::in(ReferralTrigger::ruleValues())],
            'payment_cents' => ['nullable', 'integer', 'min:0'],
            'plan_id' => ['nullable', 'uuid'],
            'billing_interval' => ['nullable', Rule::enum(BillingInterval::class)],
            'payment_source' => ['nullable', Rule::enum(ReferralPaymentSource::class)],
            'payment_sequence' => ['nullable', 'integer', 'min:1'],
            'converted_referrals' => ['nullable', 'integer', 'min:0'],
            'multiplier' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
