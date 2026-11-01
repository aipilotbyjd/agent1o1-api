<?php

namespace App\Enums\Broadcasts;

/**
 * What an in-app announcement is for. A maintenance notice carries a window
 * (both ends required) and may block writes while it is open.
 */
enum AnnouncementType: string
{
    case Banner = 'banner';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Banner => 'Banner',
            self::Maintenance => 'Maintenance',
        };
    }
}
