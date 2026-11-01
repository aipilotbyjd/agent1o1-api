<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantToolRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantToolRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `rule: null` clears the tool's rule, putting it back on its default.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*.tool' => ['required', 'string', 'max:100', 'distinct'],
            'rules.*.rule' => ['nullable', Rule::enum(AssistantToolRule::class)],
        ];
    }
}
