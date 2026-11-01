<?php

namespace App\Enums\Assistant;

enum AssistantSituationStatus: string
{
    case Open = 'open';
    case Sent = 'sent';
    case Done = 'done';
    case Dismissed = 'dismissed';
}
