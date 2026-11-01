<?php

namespace App\Enums\Assistant;

enum AssistantActionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Whether the call has run (or tried to): its stored result is what
     * the model is told from then on, so it never runs twice.
     */
    public function hasRun(): bool
    {
        return in_array($this, [self::Executed, self::Failed], true);
    }
}
