<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural validation only (shape of the payload): node-level config
 * schema and graph-level checks (cycles, dangling edges, ...) are
 * `Workflow::replaceGraph()`'s/`GraphValidator`'s job, not this request's.
 */
class ReplaceGraphRequest extends FormRequest
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
