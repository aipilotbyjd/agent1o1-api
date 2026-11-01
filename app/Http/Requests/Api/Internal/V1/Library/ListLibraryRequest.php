<?php

namespace App\Http\Requests\Api\Internal\V1\Library;

use Illuminate\Foundation\Http\FormRequest;

class ListLibraryRequest extends FormRequest
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
            'type' => ['nullable', 'in:image,file'],
            'agent_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
