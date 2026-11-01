<?php

namespace App\Enums\Agents;

enum SessionEvaluationStatus: string
{
    case Queued = 'queued';
    case Evaluating = 'evaluating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
