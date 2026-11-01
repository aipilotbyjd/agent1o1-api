<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentEvalSuiteRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'run_on_change' => ['sometimes', 'boolean'],
        ];
    }
}
