<?php

namespace App\Http\Requests\Api\V1\AgentSkill;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['required', 'string'],
            'is_shared' => ['nullable', 'boolean'],
        ];
    }
}
