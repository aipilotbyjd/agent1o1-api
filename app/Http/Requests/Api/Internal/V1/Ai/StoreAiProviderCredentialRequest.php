<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAiProviderCredentialRequest extends FormRequest
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
            'execution_provider' => ['required', 'string', Rule::in(array_keys((array) config('byok.providers')))],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scope' => ['sometimes', new Enum(ConnectorCredentialScope::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'execution_provider.in' => 'Keys for this provider aren\'t supported.',
        ];
    }
}
