<?php

namespace App\Http\Requests\Api\V1\AgentSkill;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'in:General,Research,Data,Communication,Automation,Development,Content'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:16'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:32'],
            'instructions' => ['sometimes', 'string'],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }
}
