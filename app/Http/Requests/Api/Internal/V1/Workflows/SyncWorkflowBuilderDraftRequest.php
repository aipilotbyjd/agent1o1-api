<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural validation only, like `ReplaceGraphRequest`: node configs and
 * edge endpoints are checked by `WorkflowBuilderSession::replaceDraft()`.
 */
class SyncWorkflowBuilderDraftRequest extends FormRequest
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
            'draft_lock_version' => ['required', 'integer', 'min:0'],
            'nodes' => ['present', 'array'],
            'nodes.*.key' => ['required', 'string', 'max:255'],
            'nodes.*.type' => ['required', 'string', 'max:255'],
            'nodes.*.config' => ['nullable', 'array'],
            'nodes.*.position' => ['nullable', 'array'],
            'edges' => ['present', 'array'],
            'edges.*.from' => ['required', 'string', 'max:255'],
            'edges.*.to' => ['required', 'string', 'max:255'],
            'edges.*.condition' => ['nullable', 'string', 'max:255'],
        ];
    }
}
