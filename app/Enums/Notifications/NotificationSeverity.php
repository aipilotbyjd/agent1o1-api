<?php

namespace App\Enums\Notifications;

/**
 * How urgent a member-facing notification is. It decides the tone the app
 * shows it in, whether it pops a toast, the colour of its email and which
 * chat or webhook routes it reaches (`NotificationRoute::$min_severity`).
 *
 * Operator alerts have their own `AlertSeverity`: the two never mix.
 */
enum NotificationSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';
    case Success = 'success';

    /**
     * Higher is louder. Success and info rank the same: neither needs anyone
     * to act.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Warning => 2,
            self::Info, self::Success => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * Severities at or above this one, for filtering in SQL.
     *
     * @return array<int, string>
     */
    public function andAbove(): array
    {
        return array_values(array_map(
            fn (self $severity): string => $severity->value,
            array_filter(self::cases(), fn (self $severity): bool => $severity->isAtLeast($this)),
        ));
    }

    /**
     * Discord embed colour and email accent, as a hex string.
     */
    public function color(): string
    {
        return match ($this) {
            self::Critical => '#E11D48',
            self::Warning => '#D97706',
            self::Info => '#2563EB',
            self::Success => '#059669',
        };
    }
}
