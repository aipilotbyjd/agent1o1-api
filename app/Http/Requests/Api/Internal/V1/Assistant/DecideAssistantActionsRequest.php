<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class DecideAssistantActionsRequest extends FormRequest
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
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.tool_call_id' => ['required', 'string', 'distinct'],
            'decisions.*.approve' => ['required', 'boolean'],
            'decisions.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, array{approve: bool, note: string|null}>
     */
    public function decisions(): array
    {
        return collect($this->validated('decisions'))
            ->mapWithKeys(fn (array $decision): array => [$decision['tool_call_id'] => [
                'approve' => (bool) $decision['approve'],
                'note' => $decision['note'] ?? null,
            ]])
            ->all();
    }
}
