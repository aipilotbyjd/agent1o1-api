<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentActionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAgentActionsRequest extends FormRequest
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
            'tool_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
