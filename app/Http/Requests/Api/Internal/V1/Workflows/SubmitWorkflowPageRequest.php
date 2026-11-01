<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the envelope is checked here. The input itself is validated against
 * the workflow's interface by `PageSubmissions::submit()`, the same way for
 * members and for visitors at the public link.
 */
class SubmitWorkflowPageRequest extends FormRequest
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
            'input' => ['nullable', 'array'],
            'test' => ['sometimes', 'boolean'],
            // Which draft a test run from the builder uses.
            'variant' => ['sometimes', 'in:a,b'],
        ];
    }
}
