<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConnectorCredentialRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'data' => ['sometimes', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
