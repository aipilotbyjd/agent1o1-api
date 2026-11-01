<?php

namespace App\Enums\Assistant;

enum AssistantTriggerType: string
{
    case Schedule = 'schedule';
    case Once = 'once';
    case Webhook = 'webhook';
}
