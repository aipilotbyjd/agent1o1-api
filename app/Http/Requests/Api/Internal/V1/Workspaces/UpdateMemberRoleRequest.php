<?php

namespace App\Http\Requests\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
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
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
        ];
    }
}
