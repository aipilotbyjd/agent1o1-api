<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * One change to many apps, under one reason.
 */
class BulkAppsRequest extends ReasonRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('value') === '') {
            $this->merge(['value' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->input('action');

        return [
            ...parent::rules(),
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['set_vendor', 'set_functions', 'set_featured', 'switch_on', 'switch_off'])],
            'value' => match ($action) {
                'set_vendor' => ['nullable', Rule::exists('catalog_vendors', 'slug')],
                'set_functions' => ['required', 'array', 'min:1', 'max:3'],
                'set_featured' => ['required', 'boolean'],
                default => ['nullable'],
            },
            'value.*' => $action === 'set_functions'
                ? ['required', 'distinct', Rule::exists('catalog_functions', 'slug')->using(fn ($query) => $query->where('is_system', false))]
                : ['nullable'],
        ];
    }
}
