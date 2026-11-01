<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;

class UpdateTaxonomyItemRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'label' => ['sometimes', 'string', 'min:1', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array{label?: string, sort_order?: int}
     */
    public function changes(): array
    {
        return collect($this->validated())->except('reason')->all();
    }
}
