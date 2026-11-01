<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Http\Requests\Api\Internal\V1\Workflows\Concerns\ValidatesWorkspaceFolder;
use Illuminate\Foundation\Http\FormRequest;

class MoveWorkflowsRequest extends FormRequest
{
    use ValidatesWorkspaceFolder;

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
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => ['uuid'],
            'folder_id' => ['nullable', 'uuid', $this->workspaceFolderExists(TriggerTargetType::Workflow)],
        ];
    }
}
