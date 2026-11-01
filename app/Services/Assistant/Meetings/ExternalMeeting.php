<?php

namespace App\Services\Assistant\Meetings;

use Illuminate\Support\Str;

/**
 * A meeting is external when at least one guest (other than the owner) has
 * an email domain different from the owner's.
 */
final class ExternalMeeting
{
    /**
     * @param  list<array{email: string, name?: string|null}>  $attendees
     */
    public static function isExternal(string $ownerEmail, array $attendees): bool
    {
        $ownerDomain = Str::lower(Str::after($ownerEmail, '@'));

        return collect($attendees)
            ->map(fn (array $attendee): string => Str::lower(Str::after($attendee['email'], '@')))
            ->contains(fn (string $domain): bool => $domain !== '' && $domain !== $ownerDomain);
    }
}
