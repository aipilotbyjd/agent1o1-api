<?php

namespace App\Http\Requests\Api\V1\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allow_clone' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
