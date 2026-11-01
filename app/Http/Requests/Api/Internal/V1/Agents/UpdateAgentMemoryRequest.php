<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentMemoryRequest extends FormRequest
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
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'string'],
            'type' => ['sometimes', 'nullable', 'string', 'max:30'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
