<?php

namespace App\Enums\Assistant;

enum AssistantBriefingRunStatus: string
{
    case Queued = 'queued';
    case Collecting = 'collecting';
    case Writing = 'writing';
    case Completed = 'completed';
    case Failed = 'failed';
}
