<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use Illuminate\Validation\Rule;

class StorePlanRequest extends PlanPayloadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->sharedRules('required'),
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('plans', 'slug')],
            'price_monthly' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'price_quarterly' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'price_yearly' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'price_lifetime' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
