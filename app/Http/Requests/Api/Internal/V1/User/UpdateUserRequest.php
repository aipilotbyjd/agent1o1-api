<?php

namespace App\Http\Requests\Api\Internal\V1\User;

use App\Models\User;
use App\Rules\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Emails are stored lowercased (`User::normalizeEmail()`), so the address
     * is matched and checked for uniqueness in that same spelling.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->getKey()),
                // A pending change on another account has not been applied yet, but
                // letting two accounts race for the same address would mean one of
                // them silently fails at confirmation time.
                Rule::unique('users', 'pending_email')->ignore($this->user()->getKey()),
            ],
            // Empty follows the workspace timezone.
            'timezone' => ['sometimes', 'nullable', 'string', new Timezone],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email address is already in use.',
        ];
    }
}
