<?php

namespace App\Exceptions;

use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use RuntimeException;

/**
 * An agent embedded in a workflow stopped on an action that needs approval.
 * Not a failure: `WorkflowRunner` catches it and parks the Agent node until
 * the action is decided, then resumes it through
 * `AgentRunner::resumeInConversation()`.
 */
class AgentTurnPausedException extends RuntimeException
{
    public function __construct(
        public readonly AgentSession $session,
        public readonly AgentMessage $agentMessage,
    ) {
        parent::__construct('The agent is waiting for an action to be approved.');
    }
}
