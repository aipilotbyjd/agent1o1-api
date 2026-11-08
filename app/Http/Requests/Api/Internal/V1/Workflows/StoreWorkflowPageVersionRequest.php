<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageVersion;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowPageVersionRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:'.WorkflowPageVersion::Saved.','.WorkflowPageVersion::BeforeGeneration],
        ];
    }
}
