<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use App\Enums\Billing\BillingInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckoutSubscriptionRequest extends FormRequest
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
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'interval' => ['required', new Enum(BillingInterval::class)],
        ];
    }
}
