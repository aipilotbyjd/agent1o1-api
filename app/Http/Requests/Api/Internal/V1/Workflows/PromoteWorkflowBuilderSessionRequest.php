<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class PromoteWorkflowBuilderSessionRequest extends FormRequest
{
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
            'name' => ['nullable', 'string', 'max:255'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }
}
