<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantFeedbackRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantFeedbackRequest extends FormRequest
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
            'rating' => ['required', Rule::enum(AssistantFeedbackRating::class)],
            'comment' => ['nullable', 'string', 'max:'.config('assistant.limits.feedback_comment_max_chars')],
        ];
    }
}
