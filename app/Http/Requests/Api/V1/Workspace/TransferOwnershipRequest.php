<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkspaceTransferOwnership->value);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = $this->input('user_id');
            $user = $this->user();
            $workspace = $this->route('workspace');

            if ((int) $userId === $user->id) {
                $validator->errors()->add('user_id', 'You cannot transfer ownership to yourself.');
            }

            if ($workspace && ! $workspace->members()->where('users.id', $userId)->exists()) {
                $validator->errors()->add('user_id', 'The target user is not a member of this workspace.');
            }
        });
    }
}
