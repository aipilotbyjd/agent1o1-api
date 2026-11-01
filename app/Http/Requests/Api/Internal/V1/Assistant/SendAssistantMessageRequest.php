<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class SendAssistantMessageRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:'.config('assistant.limits.message_max_chars')],
        ];
    }
}
