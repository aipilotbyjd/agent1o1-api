<?php

namespace App\Http\Requests\Api\V1\AgentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Platform-admin only. Gate here so non-admins get 403 before validation.
        return (bool) $this->user()?->can('platformAdmin');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'system_prompt' => ['sometimes', 'string'],
            'llm_provider' => ['sometimes', 'string', 'max:100'],
            'llm_model' => ['sometimes', 'string', 'max:150'],
            'llm_settings' => ['nullable', 'array'],
            'tool_configs' => ['nullable', 'array'],
            'example_conversations' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
