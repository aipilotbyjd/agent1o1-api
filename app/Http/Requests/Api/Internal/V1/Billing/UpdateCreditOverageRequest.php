<?php

namespace App\Http\Requests\Api\Internal\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `limit` is the workspace's own ceiling on overage credits per billing
 * period. It is validated against `config('billing.overage.default_limit')`
 * so the API refuses a raise outright rather than silently clamping it:
 * `CreditOverage::effectiveLimitFor()` clamps too, but a 422 is the honest
 * answer to "set my cap to ten million". `null` clears the workspace's
 * choice and falls back to that estate default.
 */
class UpdateCreditOverageRequest extends FormRequest
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
        $maximum = config('billing.overage.default_limit');

        return [
            'enabled' => ['required', 'boolean'],
            'limit' => array_filter([
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                $maximum === null ? null : 'max:'.(int) $maximum,
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'limit.max' => 'The overage cap may be lowered but not raised above :max credits per billing period.',
        ];
    }
}
