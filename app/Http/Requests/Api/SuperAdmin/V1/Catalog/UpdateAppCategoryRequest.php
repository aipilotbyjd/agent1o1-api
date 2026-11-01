<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * Re-files an integration in the editor's Apps panel: what it is used for
 * (one to three, the first being the primary) and who makes it.
 */
class UpdateAppCategoryRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'functions' => ['required', 'array', 'min:1', 'max:3'],
            'functions.*' => ['required', 'distinct', Rule::exists('catalog_functions', 'slug')->using(fn ($query) => $query->where('is_system', false))],
            'vendor' => ['nullable', Rule::exists('catalog_vendors', 'slug')],
        ];
    }
}
