<?php

namespace App\Http\Requests\Api\Internal\V1\User;

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deleting an account is permanent, so it needs more than a live token: the
 * person retypes their email address, and anyone who signs in with a
 * password must also give it. An account created through Google or GitHub
 * has a random password nobody knows, so for those the typed address alone
 * confirms.
 */
class DeleteAccountRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        return [
            'confirm_email' => ['required', 'string', Rule::in([$user->email])],
            'current_password' => [
                Rule::requiredIf(fn (): bool => app(AccountDeletionService::class)->requiresPassword($user)),
                'nullable',
                'string',
                'current_password:api',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirm_email'))) {
            $this->merge(['confirm_email' => User::normalizeEmail($this->input('confirm_email'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_email.in' => 'Type your account email address exactly to confirm.',
        ];
    }
}
