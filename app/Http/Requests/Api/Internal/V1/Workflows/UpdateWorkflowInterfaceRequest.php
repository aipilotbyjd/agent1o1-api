<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Enums\Workflows\InterfaceFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Declares a workflow's input contract. Sending an empty `fields` array
 * clears the declaration, which puts the workflow back on the derived
 * interface (`WorkflowInterface`) rather than leaving it with none.
 */
class UpdateWorkflowInterfaceRequest extends FormRequest
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
            'fields' => ['present', 'array'],
            // Keys become top-level entries of the run's `input` array, so
            // they can't carry dots: `{{ input.a.b }}` addresses inside the
            // value of field `a`, it isn't a field named `a.b`.
            'fields.*.key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_-]*$/'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(InterfaceFieldType::class)],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.help' => ['nullable', 'string', 'max:1000'],
            'fields.*.default' => ['nullable'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.value' => ['required', 'string', 'max:255'],
            'fields.*.options.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.regex' => 'An interface field key must start with a letter or underscore and contain only letters, numbers, underscores or hyphens.',
        ];
    }
}
