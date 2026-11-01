<?php

namespace App\Services\Assistant\Inbox;

use Illuminate\Support\Str;

final class MailAddress
{
    public static function bare(string $address): string
    {
        return Str::lower(trim(preg_match('/<([^>]+)>/', $address, $match) === 1 ? $match[1] : $address));
    }

    /**
     * @return list<string>
     */
    public static function list(?string $header): array
    {
        if (blank($header)) {
            return [];
        }

        return collect(str_getcsv((string) $header))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values()
            ->all();
    }

    public static function domain(string $address): string
    {
        return Str::lower(Str::after(self::bare($address), '@'));
    }

    /**
     * Senders that never read replies: no-reply addresses and mailers.
     */
    public static function isNoReply(string $address): bool
    {
        return preg_match('/(^|[.+_-])(no-?reply|do-?not-?reply|notifications?|mailer-daemon|bounce)s?@/i', self::bare($address)) === 1;
    }
}
