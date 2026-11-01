<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesSkillFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreSkillReferenceRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', ...$this->referenceContentRules()],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
