<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A form submission against a workflow's declared interface. Unlike
 * `StartRunRequest` (which accepts any input array, because an API caller
 * or a webhook payload is trusted to know the shape) this validates against
 * the contract the workflow published, so a person filling in a form gets a
 * 422 naming the field rather than a run that quietly resolves `{{ input.x }}`
 * to null.
 */
class SubmitWorkflowInterfaceRequest extends FormRequest
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
        $workflow = $this->route('workflow');

        return [
            'input' => ['nullable', 'array'],
            ...($workflow instanceof Workflow ? app(WorkflowInterface::class)->rules($workflow) : []),
        ];
    }

    /**
     * Name each field by the label the form shows, so a message reads "The
     * Topic field is required." rather than naming `input.topic`.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $workflow = $this->route('workflow');

        if (! $workflow instanceof Workflow) {
            return [];
        }

        return collect(app(WorkflowInterface::class)->describe($workflow)['fields'])
            ->mapWithKeys(fn (array $field): array => ["input.{$field['key']}" => $field['label'] ?? $field['key']])
            ->all();
    }
}
