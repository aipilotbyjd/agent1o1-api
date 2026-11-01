<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Either the `type` of a node about to be added, or the `key` of one already
 * in the draft (whose current config is then the starting point).
 */
class ConfigureWorkflowBuilderNodeRequest extends FormRequest
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
            'instruction' => ['required', 'string', 'max:4000'],
            'type' => ['required_without:key', 'nullable', 'string', 'max:255'],
            'key' => ['required_without:type', 'nullable', 'string', 'max:255'],
        ];
    }
}
