<?php

namespace App\Http\Requests\Api\Internal\V1\Runs;

use App\Models\Workflows\WorkflowApproval;
use App\Services\Workflows\ApprovalForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DecideApprovalRequest extends FormRequest
{
    /**
     * The answers, checked and typed against the step's questions. Filled in
     * by the validation pass below.
     *
     * @var array<string, mixed>
     */
    private array $checkedInputs = [];

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
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'message' => ['nullable', 'string', 'max:1000'],
            'inputs' => ['nullable', 'array'],
        ];
    }

    /**
     * Approving a step with questions needs its answers; rejecting needs none.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $approval = $this->route('approval');

            if (! $approval instanceof WorkflowApproval || $this->input('decision') !== 'approve' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $fields = $approval->nodeRun->output['approval_form']['fields'] ?? [];

            if ($fields === []) {
                return;
            }

            $result = ApprovalForm::check($fields, (array) $this->input('inputs', []));

            foreach ($result['errors'] as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("inputs.{$field}", $message);
                }
            }

            $this->checkedInputs = $result['values'];
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalInputs(): array
    {
        return $this->checkedInputs;
    }
}
