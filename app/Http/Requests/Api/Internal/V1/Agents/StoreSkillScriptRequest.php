<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Models\Agents\SkillScript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'language' => ['required', 'string', Rule::in(SkillScript::LANGUAGES)],
            'code' => ['required', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
