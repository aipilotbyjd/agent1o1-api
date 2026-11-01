<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class TestWorkflowNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `nodes` stands in for upstream node outputs (`{{ nodes.a.result }}`),
     * keyed by node key: the editor sends the last known outputs so a node
     * mid-graph can be tested without re-running everything before it.
     * `config` lets an unsaved config be tested; omitted, the stored one runs.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'input' => ['nullable', 'array'],
            'nodes' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ];
    }
}
