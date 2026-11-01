<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case AwaitingCallback = 'awaiting_callback';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * Every non-terminal status, i.e. "this run is still in flight". Returned
     * as cases (not values) since its only callers pass it straight into a
     * query builder clause, which binds a BackedEnum by its value.
     *
     * @return array<int, self>
     */
    public static function inFlight(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => ! $status->isTerminal(),
        ));
    }
}
