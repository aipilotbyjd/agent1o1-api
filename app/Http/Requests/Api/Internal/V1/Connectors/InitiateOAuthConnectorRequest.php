<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InitiateOAuthConnectorRequest extends FormRequest
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
            'redirect_uri' => ['required', 'url'],
            'scope' => ['sometimes', new Enum(ConnectorCredentialScope::class)],
            'credential_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
