<?php

namespace App\Enums\Assistant;

enum AssistantSituationStepStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
    case Skipped = 'skipped';
}
