<?php

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AttachSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill_id' => ['required', 'uuid', 'exists:agent_skills,id'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
