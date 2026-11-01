<?php

namespace App\Enums;

enum NodeRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case AwaitingCallback = 'awaiting_callback';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Skipped, self::Cancelled], true);
    }

    /**
     * Every non-terminal status, i.e. "this node run is still in flight",
     * which includes the two parked states (`AwaitingApproval`,
     * `AwaitingCallback`) as well as the two active ones. Mirrors
     * `RunStatus::inFlight()`, and is returned as cases (not values) for the
     * same reason: its callers pass it straight into a query builder clause,
     * which binds a BackedEnum by its value.
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
