<?php

namespace App\Http\Requests\Api\Internal\V1\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            // Defaults to 'team' (this table's original, workspace-shared
            // behavior) when omitted: see ConnectorCredentialScope.
            'scope' => ['sometimes', new Enum(ConnectorCredentialScope::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
