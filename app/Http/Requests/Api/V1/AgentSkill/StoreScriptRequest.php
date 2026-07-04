<?php

namespace App\Http\Requests\Api\V1\AgentSkill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'language' => ['nullable', Rule::in(['php', 'javascript'])],
            'code' => ['required', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
