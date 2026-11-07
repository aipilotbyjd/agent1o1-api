<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use App\Enums\Workspaces\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Emails are stored lowercased (`User::normalizeEmail()`), so each address
     * is matched in that same spelling.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('emails'))) {
            $this->merge(['emails' => array_map(
                fn (mixed $email): mixed => is_string($email) ? User::normalizeEmail($email) : $email,
                $this->input('emails'),
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['required', 'email'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
            'personal_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
