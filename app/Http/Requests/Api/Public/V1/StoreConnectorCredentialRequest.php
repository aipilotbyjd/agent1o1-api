<?php

namespace App\Http\Requests\Api\Public\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreConnectorCredentialRequest extends FormRequest
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
            'connector_id' => ['required', 'uuid', 'exists:connectors,id'],
            'name' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
