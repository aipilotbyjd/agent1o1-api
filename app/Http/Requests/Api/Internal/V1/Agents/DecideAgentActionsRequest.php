<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One or more decisions on waiting agent actions: see
 * `ResolveAgentActionsAction` for what each decision does.
 */
class DecideAgentActionsRequest extends FormRequest
{
    public const array DECISIONS = ['approve', 'edit', 'reject'];

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
        ];
    }

    /**
     * @return list<array{action_id: string, decision: string, arguments?: array<string, mixed>|null, note?: string|null, stop?: bool, remember?: bool}>
     */
    public function decisions(): array
    {
        return array_values($this->validated('decisions'));
    }
}
