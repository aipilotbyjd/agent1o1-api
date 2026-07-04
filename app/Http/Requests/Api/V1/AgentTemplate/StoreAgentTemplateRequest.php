<?php

namespace App\Http\Requests\Api\V1\AgentTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'system_prompt' => ['required', 'string'],
            'llm_provider' => ['required', 'string', 'max:100'],
            'llm_model' => ['required', 'string', 'max:150'],
            'llm_settings' => ['nullable', 'array'],
            'tool_configs' => ['nullable', 'array'],
            'example_conversations' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
