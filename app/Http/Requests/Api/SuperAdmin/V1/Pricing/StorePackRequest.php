<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

class StorePackRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('credit_pack_definitions', 'key')],
            'label' => ['required', 'string', 'max:100'],
            'credits' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price_cents' => ['required', 'integer', 'min:50', 'max:100000000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
