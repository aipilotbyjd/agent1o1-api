<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\EvalAssertionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * At least one assertion is required: a case that asserts nothing always
 * passes, which would quietly inflate a suite's score without testing
 * anything.
 */
class StoreAgentEvalCaseRequest extends FormRequest
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
            'input' => ['required', 'string'],
            'assertions' => ['required', 'array', 'min:1'],
            'assertions.*.type' => ['required', Rule::enum(EvalAssertionType::class)],
            'assertions.*.value' => ['required', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
