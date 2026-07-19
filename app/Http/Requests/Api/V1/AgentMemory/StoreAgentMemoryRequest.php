<?php

namespace App\Http\Requests\Api\V1\AgentMemory;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:150'],
            'value' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:30'],
            'scope' => ['nullable', 'in:agent,user'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
