<?php

namespace App\Http\Requests\Api\V1\WorkflowBuilder;

use Illuminate\Foundation\Http\FormRequest;

class SyncDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nodes' => ['present', 'array'],
            'nodes.*.id' => ['required', 'string'],
            'nodes.*.type' => ['required', 'string'],
            'nodes.*.name' => ['nullable', 'string'],
            'nodes.*.config' => ['nullable', 'array'],
            'nodes.*.position' => ['nullable', 'array'],
            'nodes.*.position.x' => ['nullable', 'numeric'],
            'nodes.*.position.y' => ['nullable', 'numeric'],
            'edges' => ['present', 'array'],
            'edges.*.source' => ['required', 'string'],
            'edges.*.target' => ['required', 'string'],
            'edges.*.sourceHandle' => ['nullable', 'string'],
            'edges.*.targetHandle' => ['nullable', 'string'],
        ];
    }
}
