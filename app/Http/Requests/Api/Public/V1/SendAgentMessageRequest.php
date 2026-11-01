<?php

namespace App\Http\Requests\Api\Public\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendAgentMessageRequest extends FormRequest
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
            'message' => ['required', 'string'],
        ];
    }
}
