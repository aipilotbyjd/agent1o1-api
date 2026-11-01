<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Pricing;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * The key is not accepted: it is on every purchase row.
 */
class UpdatePackRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'label' => ['sometimes', 'string', 'max:100'],
            'credits' => ['sometimes', 'integer', 'min:1', 'max:100000000'],
            'price_cents' => ['sometimes', 'integer', 'min:50', 'max:100000000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
