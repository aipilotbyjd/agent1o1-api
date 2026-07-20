<?php

namespace App\Http\Requests\Api\V1\AgentSkill;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAgentSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
