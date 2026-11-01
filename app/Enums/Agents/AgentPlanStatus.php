<?php

namespace App\Enums\Agents;

enum AgentPlanStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** A newer plan in the same conversation replaced it before it finished. */
    case Superseded = 'superseded';

    case Completed = 'completed';

    public function isOpen(): bool
    {
        return $this === self::Proposed || $this === self::Approved;
    }
}
