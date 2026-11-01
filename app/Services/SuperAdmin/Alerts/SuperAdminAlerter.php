<?php

namespace App\Services\SuperAdmin\Alerts;

use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Raises a Super Admin security alert: always logged, and mailed to the
 * recipients in `config/super_admin_alerts.php` when the event is enabled.
 * An alert that fails to send must never fail the request that raised it.
 */
class SuperAdminAlerter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function raise(string $event, string $summary, array $context = []): void
    {
        if (! config('super_admin_alerts.enabled') || ! config("super_admin_alerts.events.{$event}", false)) {
            return;
        }

        Log::warning("Super Admin alert: {$summary}", ['event' => $event] + $context);

        $recipients = config('super_admin_alerts.recipients.mail', []);

        if ($recipients === []) {
            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new SuperAdminAlertNotification($event, $summary, $context));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
