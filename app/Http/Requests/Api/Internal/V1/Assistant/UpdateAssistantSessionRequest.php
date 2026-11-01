<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssistantSessionRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:'.config('assistant.limits.session_title_max_chars')],
            'status' => ['sometimes', Rule::enum(AssistantSessionStatus::class)],
        ];
    }
}
