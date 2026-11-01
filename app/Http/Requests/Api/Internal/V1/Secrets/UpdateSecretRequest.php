<?php

namespace App\Http\Requests\Api\Internal\V1\Secrets;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecretRequest extends FormRequest
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
            'key' => [
                'sometimes', 'string', 'max:255', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('secrets', 'key')
                    ->where('workspace_id', $this->route('workspace')->id)
                    ->ignore($this->route('secret')->id),
            ],
            'value' => ['sometimes', 'string', 'max:16384'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_secret' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Flipping an existing secret to a readable variable would turn this
     * endpoint into the read-back path the store deliberately doesn't have:
     * the old value has to be replaced in the same request.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $becomingReadable = $this->boolean('is_secret') === false && $this->has('is_secret');

            if ($becomingReadable && $this->route('secret')->is_secret && ! $this->filled('value')) {
                $validator->errors()->add('value', 'Provide a new value when converting a secret into a readable variable: the stored one is never revealed.');
            }
        });
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
