<?php

namespace App\Nodes\Integrations\Concerns;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Reads a meeting time the way a person writes it, in the timezone they name,
 * and hands the service a point in time it cannot misread: UTC.
 */
trait ParsesMeetingTimes
{
    /**
     * @throws InvalidArgumentException
     */
    protected function utc(mixed $value, ?string $timezone, string $label): CarbonImmutable
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new InvalidArgumentException("{$label} is empty.");
        }

        $zone = trim((string) $timezone) ?: 'UTC';

        if (! in_array($zone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException("“{$zone}” is not a timezone. Use a name such as Europe/London or America/New_York.");
        }

        try {
            // A time that already carries an offset (…Z, …+01:00) keeps it; otherwise it is read in the timezone given.
            return CarbonImmutable::parse($text, $zone)->utc();
        } catch (Throwable) {
            throw new InvalidArgumentException("{$label} “{$text}” is not a date and time. Use a form such as 2026-11-05 14:30.");
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function minutes(mixed $value, int $default = 30): int
    {
        $minutes = is_numeric($value) ? (int) $value : $default;

        if ($minutes < 1 || $minutes > 1440) {
            throw new InvalidArgumentException('A meeting must last between 1 minute and 24 hours.');
        }

        return $minutes;
    }
}
