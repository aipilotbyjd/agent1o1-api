<?php

namespace App\Http\Requests\Api\Internal\V1\Runs;

use App\Enums\RunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the workspace run list. `trigger_type` matters more than it
 * looks: single-node tests are real runs (`NodeTester::TRIGGER_TYPE`) and a
 * run list usually wants them excluded, which `exclude_trigger_type` does
 * without the client having to enumerate every type it *does* want.
 */
class IndexRunsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(RunStatus::class)],
            'workflow_id' => ['nullable', 'uuid'],
            'agent_id' => ['nullable', 'uuid'],
            'runnable_type' => ['nullable', Rule::in(['workflow', 'agent_session', 'reflection_run', 'agent_session_evaluation', 'agent_eval_run'])],
            'trigger_type' => ['nullable', 'string', 'max:255'],
            'exclude_trigger_type' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
