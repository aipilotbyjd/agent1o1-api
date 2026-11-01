<?php

namespace App\Enums\Assistant;

enum AssistantFeedbackStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Ignored = 'ignored';
}
