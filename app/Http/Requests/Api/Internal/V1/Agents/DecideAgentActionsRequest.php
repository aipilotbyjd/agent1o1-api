<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One or more decisions on waiting agent actions: see
 * `ResolveAgentActionsAction` for what each decision does.
 */
class DecideAgentActionsRequest extends FormRequest
{
    /**
     * `answer` is for a question the agent asked (`AskUserTool`), and the
     * only way to answer one; `reject` skips it.
     */
    public const array DECISIONS = ['approve', 'edit', 'reject', 'answer'];

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
            'decisions' => ['required', 'array', 'min:1', 'max:100'],
            'decisions.*.action_id' => ['required', 'uuid', 'distinct'],
            'decisions.*.decision' => ['required', Rule::in(self::DECISIONS)],
            'decisions.*.arguments' => ['required_if:decisions.*.decision,edit', 'nullable', 'array'],
            'decisions.*.note' => ['nullable', 'string', 'max:2000'],
            'decisions.*.stop' => ['nullable', 'boolean'],
            'decisions.*.remember' => ['nullable', 'boolean'],
            // Text, the picked options, or a form's values keyed by field name:
            // checked against the question itself by `ResolveAgentActionsAction`.
            'decisions.*.answer' => ['required_if:decisions.*.decision,answer', 'nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_scalar($value) && ! is_array($value) && $value !== null) {
                    $fail('The answer must be text, a list of options or form values.');
                }

                if (strlen((string) json_encode($value)) > 20000) {
                    $fail('The answer is too long.');
                }
            }],
        ];
    }

    /**
     * @return list<array{action_id: string, decision: string, arguments?: array<string, mixed>|null, note?: string|null, stop?: bool, remember?: bool, answer?: mixed}>
     */
    public function decisions(): array
    {
        return array_values($this->validated('decisions'));
    }
}
