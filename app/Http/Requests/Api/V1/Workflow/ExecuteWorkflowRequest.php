<?php

namespace App\Http\Requests\Api\V1\Workflow;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowExecute->value);
    }

    public function rules(): array
    {
        return [
            'trigger_data' => ['nullable', 'array'],
        ];
    }
}
