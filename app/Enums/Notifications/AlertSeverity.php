<?php

namespace App\Enums\Notifications;

/**
 * How loud an admin alert is. Unlike `NotificationEvent` (the tenant-facing
 * catalogue exposed at `GET /notifications/events`) this never reaches a
 * workspace UI: it only decides which of the operator destinations in
 * `config/admin_alerts.php` a raised alert reaches.
 */
enum AlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Discord embed colour, as the decimal integer its API expects.
     */
    public function color(): int
    {
        return match ($this) {
            self::Info => 0x3B82F6,
            self::Warning => 0xF59E0B,
            self::Critical => 0xEF4444,
        };
    }

    /**
     * Destinations this severity reaches, before per-destination recipient
     * configuration is taken into account.
     *
     * @return array<int, string>
     */
    public function destinations(): array
    {
        return (array) config("admin_alerts.routing.{$this->value}", []);
    }
}
