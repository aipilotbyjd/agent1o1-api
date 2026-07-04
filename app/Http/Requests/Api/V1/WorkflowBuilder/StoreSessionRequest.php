<?php

namespace App\Http\Requests\Api\V1\WorkflowBuilder;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'prompt' => ['sometimes', 'nullable', 'string', 'min:3', 'max:2000'],
            'workflow_id' => ['sometimes', 'nullable', 'uuid', 'exists:workflows,id'],
            'nodes' => ['sometimes', 'array'],
            'nodes.*.id' => ['required_with:nodes', 'string'],
            'nodes.*.type' => ['required_with:nodes', 'string'],
            'nodes.*.name' => ['required_with:nodes', 'string'],
            'nodes.*.config' => ['sometimes', 'array'],
            'nodes.*.position' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'edges.*.source' => ['required_with:edges', 'string'],
            'edges.*.target' => ['required_with:edges', 'string'],
        ];
    }
}
