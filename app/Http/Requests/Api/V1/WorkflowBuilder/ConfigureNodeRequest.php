<?php

namespace App\Http\Requests\Api\V1\WorkflowBuilder;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'node_type' => ['required', 'string', 'max:100'],
            'intent' => ['required', 'string', 'max:2000'],
        ];
    }
}
