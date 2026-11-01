<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesSkillFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillReferenceRequest extends FormRequest
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
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', ...$this->referenceContentRules()],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
