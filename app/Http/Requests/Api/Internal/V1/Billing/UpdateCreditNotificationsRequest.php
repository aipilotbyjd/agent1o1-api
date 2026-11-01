<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Both settings are optional so a screen can save one switch without having
 * to resend the other. `thresholds` is `present`-style rather than
 * `required` for that reason, but when sent it replaces the whole set: a
 * threshold is removed by omitting it from the new array, and `null` resets
 * the workspace to the estate defaults.
 */
class UpdateCreditNotificationsRequest extends FormRequest
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
            'out_of_credits_enabled' => ['sometimes', 'boolean'],
            'thresholds' => [
                'sometimes',
                'nullable',
                'array',
                'max:'.(int) config('billing.credit_notifications.maximum_thresholds'),
            ],
            'thresholds.*' => ['integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'thresholds.max' => 'At most :max credit usage thresholds can be configured.',
            'thresholds.*.min' => 'A credit usage threshold is a percentage between 1 and 100.',
            'thresholds.*.max' => 'A credit usage threshold is a percentage between 1 and 100.',
        ];
    }
}
