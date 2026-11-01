<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PinWorkflowNodeRequest extends FormRequest
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
            'data' => ['nullable', 'array'],
            'node_run_id' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasData = $this->filled('data');
            $hasNodeRunId = $this->filled('node_run_id');

            if ($hasData === $hasNodeRunId) {
                $validator->errors()->add('data', 'Provide exactly one of `data` or `node_run_id`.');
            }
        });
    }
}
