<?php

namespace App\Enums\Operations;

/**
 * Where a task stands right now. An automatic check maps onto the same four
 * states: all clear is `Done`, a warning is `Due`, a failure is `Overdue`.
 */
enum TaskStatus: string
{
    case Done = 'done';
    case Due = 'due';
    case Overdue = 'overdue';
    case Snoozed = 'snoozed';

    public function needsAttention(): bool
    {
        return in_array($this, [self::Due, self::Overdue], true);
    }
}
