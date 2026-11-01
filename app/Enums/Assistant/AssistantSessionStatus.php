<?php

namespace App\Enums\Assistant;

enum AssistantSessionStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
