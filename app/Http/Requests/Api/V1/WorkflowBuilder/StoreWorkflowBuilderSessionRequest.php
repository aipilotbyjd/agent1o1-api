<?php

namespace App\Http\Requests\Api\V1\WorkflowBuilder;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowBuilderSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string', 'max:5000'],
            'workflow_id' => ['nullable', 'uuid', 'exists:workflows,id'],
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
        ];
    }
}
