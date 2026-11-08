<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowPageGenerationRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
            'variant' => ['nullable', 'in:a,b'],
            'keep_theme' => ['nullable', 'boolean'],
        ];
    }
}
