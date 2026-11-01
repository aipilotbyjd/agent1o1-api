<?php

namespace App\Http\Requests\Api\Internal\V1\Nodes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomNodeRequest extends FormRequest
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
            'category_id' => ['sometimes', 'uuid', 'exists:node_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'config_schema' => ['sometimes', 'array'],
            'input_schema' => ['sometimes', 'nullable', 'array'],
            'output_schema' => ['sometimes', 'nullable', 'array'],
            'credential_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
