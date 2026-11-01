<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\AlertSeverity;
use App\Notifications\Admin\AdminAlertNotification;
use App\Notifications\Channels\AdminAlertChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;

/**
 * Operator-facing alerting: conditions the people running the platform need to
 * know about (a workspace burning through its credit limit, and so on), as
 * opposed to `NotificationDispatcher`, which notifies workspace members about
 * things happening in their own workspace.
 *
 * The two systems are deliberately independent. There are no admin user
 * accounts, so this one has no notifiables, no preferences and no database
 * table: destinations come from `config/admin_alerts.php` alone. Alert keys
 * are plain strings and must never be added to `NotificationEvent`, which
 * backs the customer-facing `GET /notifications/events` catalogue.
 *
 * Adding an alert is a single `raise()` call from wherever the condition is
 * detected: no class, no registration.
 */
class AdminAlerts
{
    private const string CACHE_PREFIX = 'admin-alert:';

    /**
     * Raise an alert, returning whether it was actually dispatched. `false`
     * means it was suppressed: alerting is off, nothing is configured for
     * this severity, or an identical alert is still inside its throttle
     * window, never that delivery failed.
     *
     * @param  string  $key  Stable dot-notation identifier, e.g. `usage.threshold_crossed`.
     * @param  array<string, mixed>  $context  Rendered into the payload; secret-looking keys are redacted.
     * @param  string|null  $throttleKey  Defaults to `$key`. Pass a narrower value (e.g. per workspace) so one noisy tenant cannot mute the alert for everyone else.
     */
    public function raise(
        string $key,
        string $title,
        ?string $body = null,
        array $context = [],
        AlertSeverity $severity = AlertSeverity::Warning,
        ?string $throttleKey = null,
    ): bool {
        if (! config('admin_alerts.enabled')) {
            return false;
        }

        $mailRecipients = $this->mailRecipients($severity);
        $destinations = $this->destinations($severity);

        $channels = [];

        if ($destinations !== []) {
            $channels[] = AdminAlertChannel::class;
        }

        if ($mailRecipients !== []) {
            $channels[] = 'mail';
        }

        if ($channels === []) {
            return false;
        }

        if (! $this->claimThrottleWindow($throttleKey ?? $key)) {
            return false;
        }

        $notification = new AdminAlertNotification(
            key: $key,
            title: $title,
            body: $body,
            context: $this->redact($context),
            severity: $severity,
            channels: $channels,
            destinations: $destinations,
        );

        $this->notifiableFor($mailRecipients)->notify($notification);

        return true;
    }

    /**
     * On-demand notifiable rather than a `User`: see the class docblock.
     *
     * @param  array<int, string>  $mailRecipients
     */
    private function notifiableFor(array $mailRecipients): AnonymousNotifiable
    {
        $notifiable = new AnonymousNotifiable;

        if ($mailRecipients !== []) {
            $notifiable->route('mail', $mailRecipients);
        }

        return $notifiable;
    }

    /**
     * Atomic claim on the window: `Cache::add()` only writes when the key is
     * absent, so two processes detecting the same condition at once cannot
     * both deliver.
     */
    private function claimThrottleWindow(string $throttleKey): bool
    {
        $seconds = (int) config('admin_alerts.throttle_seconds');

        if ($seconds <= 0) {
            return true;
        }

        return Cache::add(self::CACHE_PREFIX.sha1($throttleKey), true, $seconds);
    }

    /**
     * Destinations this severity reaches that also have a URL configured: a
     * half-configured environment degrades quietly instead of erroring.
     *
     * @return array<int, array{type: string, url: string}>
     */
    private function destinations(AlertSeverity $severity): array
    {
        $routed = $severity->destinations();
        $destinations = [];

        foreach (['discord', 'webhook'] as $type) {
            $url = config("admin_alerts.recipients.{$type}");

            if (in_array($type, $routed, true) && filled($url)) {
                $destinations[] = ['type' => $type, 'url' => (string) $url];
            }
        }

        return $destinations;
    }

    /**
     * @return array<int, string>
     */
    private function mailRecipients(AlertSeverity $severity): array
    {
        if (! in_array('mail', $severity->destinations(), true)) {
            return [];
        }

        return array_values(array_filter((array) config('admin_alerts.recipients.mail')));
    }

    /**
     * Alert context leaves the application in a Discord/webhook payload, so
     * anything named like a credential is masked first, at every depth.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $patterns = (array) config('admin_alerts.redact_keys');

        $redacted = [];

        foreach ($context as $key => $value) {
            $redacted[$key] = match (true) {
                $this->isSecret((string) $key, $patterns) => '[redacted]',
                is_array($value) => $this->redact($value),
                default => $value,
            };
        }

        return $redacted;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function isSecret(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains(mb_strtolower($key), mb_strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }
}
