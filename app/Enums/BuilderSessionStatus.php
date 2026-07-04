<?php

namespace App\Enums;

enum BuilderSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
            self::Failed => 'Failed',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Active;
    }
}
