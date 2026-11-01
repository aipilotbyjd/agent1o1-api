<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `draft_lock_version` is the one the client last loaded; when sent, a
 * restore over newer edits (the assistant's, most likely) is a 409 instead
 * of silently discarding them.
 */
class RestoreWorkflowBuilderDraftVersionRequest extends FormRequest
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
            'draft_lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
