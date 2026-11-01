<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * Changes some of an app, and only those: whatever is sent is applied and the
 * rest is left alone. A reason is required, as for any catalog change.
 */
class UpdateAppRequest extends ReasonRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['vendor', 'description', 'status_note'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'icon' => ['sometimes', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'functions' => ['sometimes', 'array', 'min:1', 'max:3'],
            'functions.*' => ['required', 'distinct', Rule::exists('catalog_functions', 'slug')->using(fn ($query) => $query->where('is_system', false))],
            'vendor' => ['sometimes', 'nullable', Rule::exists('catalog_vendors', 'slug')],
            'status' => ['sometimes', Rule::in(['operational', 'degraded', 'maintenance'])],
            'status_note' => ['sometimes', 'nullable', 'string', 'max:300'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The fields to change, without the reason.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return collect($this->validated())->except('reason')->all();
    }
}
