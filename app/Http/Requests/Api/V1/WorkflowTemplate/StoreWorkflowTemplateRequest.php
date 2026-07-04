<?php

namespace App\Http\Requests\Api\V1\WorkflowTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Platform-admin only. Gate here so non-admins get 403 before validation.
        return (bool) $this->user()?->can('platformAdmin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'nodes' => ['required', 'array'],
            'edges' => ['nullable', 'array'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
