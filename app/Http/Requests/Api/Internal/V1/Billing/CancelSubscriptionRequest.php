<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use App\Enums\Billing\CancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Both fields are optional on purpose: the cancellation screen always sends a
 * reason, but cancelling must never fail because one is missing.
 */
class CancelSubscriptionRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', new Enum(CancellationReason::class)],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
