<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Billing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

class RefundRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'payment_intent' => ['required', 'string', 'regex:/^pi_[A-Za-z0-9_]+$/'],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
