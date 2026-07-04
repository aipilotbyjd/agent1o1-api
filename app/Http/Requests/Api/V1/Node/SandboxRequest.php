<?php

namespace App\Http\Requests\Api\V1\Node;

use Illuminate\Foundation\Http\FormRequest;

class SandboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:150'],
            'config' => ['nullable', 'array'],
            'input' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
        ];
    }
}
