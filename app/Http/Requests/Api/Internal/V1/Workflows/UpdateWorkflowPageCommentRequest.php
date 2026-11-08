<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowPageCommentRequest extends FormRequest
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
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'resolved' => ['sometimes', 'boolean'],
        ];
    }
}
