<?php

namespace App\Http\Requests\Api\Internal\V1\Onboarding;

use App\Enums\Workspaces\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamRequest extends FormRequest
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
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['required', 'email'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
            'personal_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
