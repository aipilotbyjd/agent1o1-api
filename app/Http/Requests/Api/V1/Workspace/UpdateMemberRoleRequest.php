<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Authorization\WorkspaceContext;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $target = $this->route('user');

        if (! $this->user()->can(Permission::MemberUpdate->value)) {
            return false;
        }

        // Cannot change the owner's role
        if ($target && $workspace->owner_id === $target->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(Role::assignableValues())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('role') === Role::Admin->value
                && app(WorkspaceContext::class)->role !== Role::Owner
            ) {
                $validator->errors()->add('role', 'Only the workspace owner can assign the admin role.');
            }
        });
    }
}
