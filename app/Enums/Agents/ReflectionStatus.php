<?php

namespace App\Enums\Agents;

enum ReflectionStatus: string
{
    case Pending = 'pending';
    case Dismissed = 'dismissed';
    case Applied = 'applied';
    case Superseded = 'superseded';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
