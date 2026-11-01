<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

class DryRunWorkflowRequest extends ValidateWorkflowRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Stands in for the run input the graph would receive, so
            // `{{ input.* }}` templates resolve against something real.
            'input' => ['nullable', 'array'],
        ];
    }
}
