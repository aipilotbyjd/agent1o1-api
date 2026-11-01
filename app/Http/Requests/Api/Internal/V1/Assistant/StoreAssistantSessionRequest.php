<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssistantSessionRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:'.config('assistant.limits.session_title_max_chars')],
            'incognito' => ['sometimes', 'boolean'],
        ];
    }
}
