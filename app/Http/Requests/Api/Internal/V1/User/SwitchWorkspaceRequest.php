<?php

namespace App\Http\Requests\Api\Internal\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class SwitchWorkspaceRequest extends FormRequest
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
            'workspace_id' => ['required', 'uuid', 'exists:workspaces,id'],
        ];
    }
}
