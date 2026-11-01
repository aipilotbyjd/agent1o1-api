<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class DestroyConnectorCredentialRequest extends FormRequest
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
            'replace_with' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
