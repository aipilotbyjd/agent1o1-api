<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkspaceUpdate->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
