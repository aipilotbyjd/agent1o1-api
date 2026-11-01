<?php

namespace App\Enums\Agents;

enum ReflectionRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Skipped, self::Failed], true);
    }
}
