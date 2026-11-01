<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Enums\Billing\CouponDuration;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends StepUpRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{3,40}$/', Rule::unique('pricing_coupons', 'code')],
            'percent_off' => ['nullable', 'integer', 'min:1', 'max:100', 'required_without:amount_off_cents', 'prohibits:amount_off_cents'],
            'amount_off_cents' => ['nullable', 'integer', 'min:1', 'max:100000000', 'required_without:percent_off'],
            'duration' => ['required', Rule::enum(CouponDuration::class)],
            'duration_in_months' => ['required_if:duration,'.CouponDuration::Repeating->value, 'nullable', 'integer', 'min:1', 'max:36'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'redeem_by' => ['nullable', 'date', 'after:now'],
            'plan_ids' => ['sometimes', 'array'],
            'plan_ids.*' => ['uuid', 'distinct', Rule::exists('plans', 'id')],
        ];
    }
}
