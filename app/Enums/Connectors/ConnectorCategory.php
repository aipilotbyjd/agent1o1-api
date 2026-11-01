<?php

namespace App\Enums\Connectors;

/**
 * How the Apps catalog groups connectors. Owned by the backend so a new
 * connector lands in the right tab without a frontend release.
 */
enum ConnectorCategory: string
{
    case Communication = 'communication';
    case Productivity = 'productivity';
    case Storage = 'storage';
    case Developer = 'developer';
    case Marketing = 'marketing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Communication => 'Communication',
            self::Productivity => 'Productivity',
            self::Storage => 'Files & Storage',
            self::Developer => 'Developer',
            self::Marketing => 'Marketing',
            self::Other => 'Other',
        };
    }

    /**
     * A name from the frontend's icon set, like `Connector::icon`.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Communication => 'message',
            self::Productivity => 'briefcase',
            self::Storage => 'folder',
            self::Developer => 'code',
            self::Marketing => 'megaphone',
            self::Other => 'grid',
        };
    }
}
