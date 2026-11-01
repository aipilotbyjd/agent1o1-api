<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approving a plan, optionally trimming it first: `skip_step_ids` drops
 * steps the person doesn't want, and `execute` tells the agent to carry the
 * plan out straight away instead of waiting for the next message.
 */
class ApproveAgentPlanRequest extends FormRequest
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
            'skip_step_ids' => ['nullable', 'array'],
            'skip_step_ids.*' => ['string'],
            'note' => ['nullable', 'string', 'max:2000'],
            'execute' => ['nullable', 'boolean'],
        ];
    }
}
