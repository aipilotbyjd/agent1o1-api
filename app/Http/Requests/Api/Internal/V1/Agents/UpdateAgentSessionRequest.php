<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentSessionStatus;
use App\Enums\Agents\AutonomyMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentSessionRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(AgentSessionStatus::class)],
            // Overrides the agent's mode for this conversation only; null goes back to the agent's.
            'autonomy_mode' => ['sometimes', 'nullable', Rule::enum(AutonomyMode::class)],
            'test_mode' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
