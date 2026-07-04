<?php

namespace App\Http\Requests\Api\V1\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class ImportWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'nodes' => ['required', 'array'],
            'edges' => ['nullable', 'array'],
        ];
    }
}
