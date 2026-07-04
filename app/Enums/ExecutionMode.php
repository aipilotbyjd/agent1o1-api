<?php

namespace App\Enums;

enum ExecutionMode: string
{
    case Manual = 'manual';
    case Webhook = 'webhook';
    case Polling = 'polling';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Webhook => 'Webhook',
            self::Polling => 'Polling',
            self::Scheduled => 'Scheduled',
        };
    }
}
