<?php

namespace App\Enums\Assistant;

enum AssistantMeetingPrepStatus: string
{
    case None = 'none';
    case Preparing = 'preparing';
    case Prepared = 'prepared';
    case Failed = 'failed';
}
