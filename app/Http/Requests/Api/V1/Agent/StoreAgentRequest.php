<?php

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    use AdvancedAgentRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->advancedRules(),
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['required', 'string'],
            'model' => ['nullable', 'string', 'max:150'],
            'provider' => ['nullable', 'string', 'max:50'],
            'max_steps' => ['nullable', 'integer', 'min:1', 'max:50'],
            'timeout_seconds' => ['nullable', 'integer', 'min:10', 'max:600'],
            'is_active' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'default_workflow_id' => ['nullable', 'uuid', 'exists:workflows,id'],
            'tools' => ['nullable', 'array'],
            'tools.*.node_type' => ['required_with:tools', 'string', 'max:150'],
            'tools.*.tool_name' => ['nullable', 'string', 'max:150'],
            'tools.*.tool_description' => ['nullable', 'string', 'max:1000'],
            'tools.*.is_enabled' => ['nullable', 'boolean'],
            'tools.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
