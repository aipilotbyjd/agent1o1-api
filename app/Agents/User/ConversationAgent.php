<?php

namespace App\Agents\User;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(15)]
#[Timeout(180)]
class ConversationAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * @param  list<Tool>  $availableTools
     */
    public function __construct(
        private string $systemPrompt,
        private array $availableTools = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return $this->availableTools;
    }
}
