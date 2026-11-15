<?php

namespace App\Enums\Changelog;

/**
 * Who may read an entry: anyone (the public page and feeds), any signed-in
 * member, or only the members an `audience` definition selects.
 */
enum ChangelogVisibility: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Audience = 'audience';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Authenticated => 'Signed-in members',
            self::Audience => 'Selected audience',
        };
    }
}
