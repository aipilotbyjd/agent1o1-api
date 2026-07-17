<?php

namespace App\Http\Requests\Api\V1\Workflow;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
{
    use PreservesGraphPayload;

    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowUpdate->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:7'],
            'folder_id' => ['nullable', 'uuid', 'exists:folders,id'],
            'error_workflow_id' => ['nullable', 'uuid', 'exists:workflows,id'],
            'max_concurrent_executions' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'is_favorite' => ['sometimes', 'boolean'],
            'nodes' => ['sometimes', 'array'],
            'nodes.*.id' => ['required_with:nodes', 'string'],
            'nodes.*.type' => ['required_with:nodes', 'string'],
            'edges' => ['sometimes', 'array'],
            'edges.*.source' => ['required_with:edges', 'string'],
            'edges.*.target' => ['required_with:edges', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['uuid', 'exists:tags,id'],
        ];
    }
}
