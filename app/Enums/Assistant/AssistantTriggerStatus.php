<?php

namespace App\Enums\Assistant;

enum AssistantTriggerStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
}
