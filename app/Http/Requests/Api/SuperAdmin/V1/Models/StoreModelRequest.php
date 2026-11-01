<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * A new public catalog entry. It is created switched off: it goes live only
 * once it has a route that works (see `UpdateModelRequest`).
 */
class StoreModelRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', Rule::unique('model_catalog', 'slug')],
            ...ModelFields::rules(required: true),
        ];
    }
}
