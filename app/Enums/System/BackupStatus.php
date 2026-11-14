<?php

namespace App\Enums\System;

/**
 * `Completed` and `Failed` are final: a finished backup record never changes.
 */
enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
