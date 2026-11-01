<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * One action on many templates of one type, under one reason.
 */
class BulkTemplatesRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['publish', 'unpublish', 'feature', 'unfeature'])],
        ];
    }
}
