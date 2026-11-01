<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantInboxDraftMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInboxSettingsRequest extends FormRequest
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
            'draft_mode' => ['sometimes', Rule::enum(AssistantInboxDraftMode::class)],
            'drafting_instructions' => ['sometimes', 'nullable', 'string', 'max:'.config('assistant.inbox.drafting_instructions_max_chars')],
            'known_senders_only' => ['sometimes', 'boolean'],
            'skip_existing_labels' => ['sometimes', 'boolean'],
        ];
    }
}
