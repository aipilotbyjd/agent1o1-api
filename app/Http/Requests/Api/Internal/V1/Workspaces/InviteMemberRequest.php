<?php

namespace App\Http\Requests\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
        ];
    }
}
