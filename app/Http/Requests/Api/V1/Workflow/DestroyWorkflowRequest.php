<?php

namespace App\Http\Requests\Api\V1\Workflow;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class DestroyWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowDelete->value);
    }

    public function rules(): array
    {
        return [];
    }
}
