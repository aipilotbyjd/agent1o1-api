<?php

namespace App\Enums\Agents;

enum SubagentTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';

    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value, self::AwaitingApproval->value];
    }
}
