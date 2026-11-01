<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesSkillFields;
use App\Models\Agents\SkillScript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
{
    use ValidatesSkillFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->skillFieldRules();

        return [
            ...$rules,
            'name' => ['required', ...$rules['name']],
            'slug' => ['nullable', ...$rules['slug']],
            'instructions' => ['required', ...$rules['instructions']],
            'is_shared' => ['sometimes', ...$rules['is_shared']],
            'references' => ['sometimes', 'array', 'max:50'],
            'references.*.title' => ['required', 'string', 'max:255'],
            'references.*.content' => ['required', ...$this->referenceContentRules()],
            'scripts' => ['sometimes', 'array', 'max:20'],
            'scripts.*.name' => ['required', 'string', 'max:255'],
            'scripts.*.description' => ['nullable', 'string'],
            'scripts.*.language' => ['required', 'string', Rule::in(SkillScript::LANGUAGES)],
            'scripts.*.code' => ['required', 'string'],
        ];
    }
}
