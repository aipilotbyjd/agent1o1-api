<?php

namespace App\Http\Requests\Api\Internal\V1\Secrets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecretRequest extends FormRequest
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
            // Constrained to the charset `SafePattern` allows inside `{{ }}`:
            // a key that can't be referenced from a node config would be
            // storable but useless.
            'key' => [
                'required', 'string', 'max:255', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('secrets', 'key')->where('workspace_id', $this->route('workspace')->id),
            ],
            'value' => ['required', 'string', 'max:16384'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_secret' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The key must be uppercase letters, digits and underscores, starting with a letter (for example API_TOKEN).',
        ];
    }
}
