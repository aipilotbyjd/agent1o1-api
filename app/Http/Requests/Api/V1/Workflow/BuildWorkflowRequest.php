<?php

namespace App\Http\Requests\Api\V1\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class BuildWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:5000'],
            'save' => ['nullable', 'boolean'],
        ];
    }
}
