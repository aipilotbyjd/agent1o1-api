<?php

namespace App\Ai\Assistant;

use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\Runtime\ConversationHistory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * A member's personal assistant as a `Laravel\Ai` agent, built per turn by
 * `TurnRunner`: instructions, tools and history are handed in, so this
 * class only exposes them. Separate from `App\Ai\Agents\WorkspaceAgent` by
 * design: see docs/ASSISTANT_PLAN.md.
 */
class AssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  array<int, Tool|object>  $tools
     */
    public function __construct(
        private readonly string $instructions,
        private readonly AssistantSession $session,
        private readonly array $tools = [],
        private readonly ?string $excludeMessageId = null,
        private readonly ?string $resumingMessageId = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * @return iterable<int, Tool|object>
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return (new ConversationHistory($this->session, $this->excludeMessageId, $this->resumingMessageId))->messages();
    }

    public function maxSteps(): int
    {
        return (int) config('assistant.runtime.max_steps');
    }
}
