<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesSkillFields;
use App\Models\Agents\Skill;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillRequest extends FormRequest
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
        $skill = $this->route('skill');

        return array_map(
            fn (array $rules): array => ['sometimes', ...$rules],
            $this->skillFieldRules($skill instanceof Skill ? $skill : null),
        );
    }
}
