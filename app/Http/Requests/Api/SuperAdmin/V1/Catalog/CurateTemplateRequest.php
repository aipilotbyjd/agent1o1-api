<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;

/**
 * Featured templates come first in the gallery, then by `sort_order`, then newest.
 */
class CurateTemplateRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array{is_featured?: bool, sort_order?: int}
     */
    public function changes(): array
    {
        return collect($this->validated())->except('reason')->all();
    }
}
