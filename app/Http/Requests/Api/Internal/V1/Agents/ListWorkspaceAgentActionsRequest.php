<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\AgentActionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWorkspaceAgentActionsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(AgentActionStatus::class)],
            'agent_id' => ['nullable', 'uuid'],
            'effect' => ['nullable', Rule::enum(ActionEffect::class)],
            'risk' => ['nullable', Rule::enum(ActionRisk::class)],
        ];
    }
}
