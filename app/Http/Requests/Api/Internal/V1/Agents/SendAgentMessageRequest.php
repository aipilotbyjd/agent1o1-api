<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Http\Requests\Concerns\ValidatesMessageAttachments;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SendAgentMessageRequest extends FormRequest
{
    use ValidatesMessageAttachments;

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
            'message' => ['required', 'string'],
            // A skill picked for this message (`/` in the chat); must be
            // attached to the agent: see `chosenSkill()`.
            'skill_id' => ['nullable', 'uuid'],
            // Retrying or editing an earlier message: it and everything after
            // it are dropped, so the model never sees the abandoned turn.
            'replaces_message_id' => [
                'nullable',
                'uuid',
                Rule::exists('agent_messages', 'id')
                    ->where('agent_session_id', $this->route('session')?->id)
                    ->where('role', AgentMessageRole::User->value),
            ],
            // Sent as multipart `attachments[]`. Each file is stored as an
            // artifact on the new message and handed to the model with it.
            ...$this->attachmentRules(),
        ];
    }

    /**
     * The picked skill, refused unless it is attached to `$agent`: a member
     * may only point the agent at skills it was given.
     *
     * @throws ValidationException
     */
    public function chosenSkill(Agent $agent): ?Skill
    {
        $skillId = $this->validated('skill_id');

        if ($skillId === null) {
            return null;
        }

        return $agent->skills()->whereKey($skillId)->first()
            ?? throw ValidationException::withMessages(['skill_id' => 'That skill is not attached to this agent.']);
    }
}
