<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class ClonePageToWorkspaceRequest extends FormRequest
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
            'target_workspace_id' => ['required', 'uuid'],
            'workflow_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
