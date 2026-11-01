<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssistantStyleRequest extends FormRequest
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
            'notes' => ['present', 'nullable', 'string', 'max:'.config('assistant.limits.style_max_chars')],
        ];
    }
}
