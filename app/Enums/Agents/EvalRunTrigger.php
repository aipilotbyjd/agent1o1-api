<?php

namespace App\Enums\Agents;

/**
 * What started an `AgentEvalRun`.
 *
 * An `AgentChange` run is started by `RunEvalsOnAgentChangeJob` with nobody
 * watching, so it simulates every action that would change something instead
 * of running it: an instructions edit must not be able to send real emails.
 */
enum EvalRunTrigger: string
{
    case Manual = 'manual';
    case AgentChange = 'agent_change';

    public function simulatesActions(): bool
    {
        return $this === self::AgentChange;
    }
}
