<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `graph` is optional: omit it to check the saved draft, or send the canvas
 * as it stands to check edits that haven't been saved yet.
 */
class ValidateWorkflowRequest extends FormRequest
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
            'graph' => ['nullable', 'array'],
            'graph.nodes' => ['required_with:graph', 'array'],
            'graph.nodes.*.key' => ['required', 'string', 'max:255'],
            'graph.nodes.*.type' => ['required', 'string', 'max:255'],
            'graph.nodes.*.config' => ['nullable', 'array'],
            'graph.edges' => ['nullable', 'array'],
            'graph.edges.*.from' => ['required', 'string', 'max:255'],
            'graph.edges.*.to' => ['required', 'string', 'max:255'],
            'graph.edges.*.condition' => ['nullable', 'string', 'max:255'],
        ];
    }
}
