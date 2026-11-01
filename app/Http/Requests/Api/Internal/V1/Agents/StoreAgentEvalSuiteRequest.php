<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentEvalSuiteRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'run_on_change' => ['sometimes', 'boolean'],
        ];
    }
}
