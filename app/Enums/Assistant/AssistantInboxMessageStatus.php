<?php

namespace App\Enums\Assistant;

enum AssistantInboxMessageStatus: string
{
    case Classified = 'classified';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
