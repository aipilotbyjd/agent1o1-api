<?php

namespace App\Enums\Assistant;

enum AssistantTurnStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Holds the session: a new message waits in the queue until it ends.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public static function inFlightValues(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
