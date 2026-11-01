<?php

namespace App\Services\Timezones;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * The one list of timezones the app offers, accepts and stores: current IANA
 * names, as PHP knows them.
 *
 * Browsers name a few zones by names IANA has since retired (their ICU data
 * keeps them for stability), so India reports itself as "Asia/Calcutta".
 * Those are accepted and stored under the current name; anything else
 * unknown is refused.
 */
final class Timezones
{
    /**
     * The retired names browsers still use, by the current name for each.
     * ICU froze these, so the list doesn't grow; `TimezonesTest` checks it
     * against ICU's own mapping where the runtime has one.
     */
    public const array ALIASES = [
        'Africa/Asmera' => 'Africa/Asmara',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Catamarca' => 'America/Argentina/Catamarca',
        'America/Coral_Harbour' => 'America/Atikokan',
        'America/Cordoba' => 'America/Argentina/Cordoba',
        'America/Godthab' => 'America/Nuuk',
        'America/Indianapolis' => 'America/Indiana/Indianapolis',
        'America/Jujuy' => 'America/Argentina/Jujuy',
        'America/Louisville' => 'America/Kentucky/Louisville',
        'America/Mendoza' => 'America/Argentina/Mendoza',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Europe/Kiev' => 'Europe/Kyiv',
        'Pacific/Enderbury' => 'Pacific/Kanton',
        'Pacific/Ponape' => 'Pacific/Pohnpei',
        'Pacific/Truk' => 'Pacific/Chuuk',
    ];

    public const string DEFAULT = 'UTC';

    /**
     * Every current IANA name, alphabetically.
     *
     * @return list<string>
     */
    public static function ids(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * `$zone` under its current name, or null when it isn't a timezone.
     */
    public static function canonical(mixed $zone): ?string
    {
        if (! is_string($zone) || $zone === '') {
            return null;
        }

        $zone = self::ALIASES[$zone] ?? $zone;

        return in_array($zone, self::ids(), true) ? $zone : null;
    }

    /**
     * `$zone` under its current name, or the default when it isn't a timezone.
     */
    public static function resolve(mixed $zone, string $default = self::DEFAULT): string
    {
        return self::canonical($zone) ?? self::canonical($default) ?? self::DEFAULT;
    }

    /**
     * The picker's entries: each name with its UTC offset right now, so
     * "Asia/Kolkata (UTC+05:30)".
     *
     * @return list<array{id: string, label: string}>
     */
    public static function options(): array
    {
        $now = CarbonImmutable::now();

        return array_map(fn (string $id): array => [
            'id' => $id,
            'label' => $id === 'UTC' ? 'UTC' : $id.' (UTC'.$now->setTimezone($id)->format('P').')',
        ], self::ids());
    }
}
