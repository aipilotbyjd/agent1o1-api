<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\EvalAssertionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentEvalCaseRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'input' => ['sometimes', 'string'],
            'assertions' => ['sometimes', 'array', 'min:1'],
            'assertions.*.type' => ['required', Rule::enum(EvalAssertionType::class)],
            'assertions.*.value' => ['required', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
