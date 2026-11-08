<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageEvent;
use Illuminate\Foundation\Http\FormRequest;

class RecordWorkflowPageStartRequest extends FormRequest
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
        return ['type' => ['required', 'in:'.WorkflowPageEvent::Start]];
    }
}
