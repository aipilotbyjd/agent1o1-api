<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Http\Requests\Api\Internal\V1\Workflows\Concerns\ValidatesWorkspaceFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('workflows', 'slug')->where('workspace_id', $this->route('workspace')?->id)],
            'description' => ['nullable', 'string'],
            'folder_id' => ['nullable', 'uuid', $this->workspaceFolderExists(TriggerTargetType::Workflow)],
        ];
    }
}
