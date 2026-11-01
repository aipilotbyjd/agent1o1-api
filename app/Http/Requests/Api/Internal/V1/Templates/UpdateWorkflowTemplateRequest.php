<?php

namespace App\Http\Requests\Api\Internal\V1\Templates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'visibility' => ['sometimes', 'string', 'in:private,public'],
        ];
    }
}
