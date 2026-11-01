<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'visibility' => ['nullable', 'string', 'in:private,public'],
        ];
    }
}
