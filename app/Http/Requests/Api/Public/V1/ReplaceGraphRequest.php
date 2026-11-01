<?php

namespace App\Http\Requests\Api\Public\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors the internal builder's graph payload. Node `position` is accepted
 * but optional: an integrator writing a graph programmatically has no
 * canvas coordinates to supply, and the editor lays out positionless nodes
 * itself.
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
