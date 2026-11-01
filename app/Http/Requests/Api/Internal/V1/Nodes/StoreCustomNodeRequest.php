<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomNodeRequest extends FormRequest
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
            'category_id' => ['required', 'uuid', 'exists:node_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'config_schema' => ['required', 'array'],
            'input_schema' => ['nullable', 'array'],
            'output_schema' => ['nullable', 'array'],
            'credential_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
