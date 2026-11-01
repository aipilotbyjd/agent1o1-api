<?php

namespace App\Enums\Assistant;

enum AssistantToolRule: string
{
    case Allow = 'allow';
    case Ask = 'ask';
    case Deny = 'deny';
}
