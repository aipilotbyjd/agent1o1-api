<?php

namespace App\Http\Requests\Api\Public\V1;

use Illuminate\Foundation\Http\FormRequest;

class PublishWorkflowRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
