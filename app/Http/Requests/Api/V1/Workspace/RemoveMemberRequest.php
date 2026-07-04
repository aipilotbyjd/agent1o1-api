<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class RemoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $target = $this->route('user');

        if (! $this->user()->can(Permission::MemberRemove->value)) {
            return false;
        }

        // Cannot remove the owner
        if ($target && $workspace->owner_id === $target->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
