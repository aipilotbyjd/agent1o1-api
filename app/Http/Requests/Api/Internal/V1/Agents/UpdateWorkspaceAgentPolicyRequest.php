<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AutonomyMode;
use App\Http\Requests\Api\Internal\V1\Agents\Concerns\ValidatesApprovalRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceAgentPolicyRequest extends FormRequest
{
    use ValidatesApprovalRules;

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
            'max_autonomy_mode' => ['sometimes', 'nullable', Rule::enum(AutonomyMode::class)],
            'allow_destructive_in_autopilot' => ['sometimes', 'boolean'],
            'approval_ttl_minutes' => ['sometimes', 'integer', 'min:5', 'max:43200'],
            'allow_chat_approvals' => ['sometimes', 'boolean'],
            ...$this->guardrailRules(),
        ];
    }
}
