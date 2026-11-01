<?php

namespace App\Console\Commands\Admin;

use App\Enums\Notifications\AlertSeverity;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Verifies that admin alert delivery is wired correctly in an environment
 * without waiting for a real condition to occur. Uses a random throttle key so
 * it can be run repeatedly, and reports which destinations the chosen severity
 * actually resolves to: a severity routed only to destinations that have no
 * URL configured sends nothing, which is exactly what this surfaces.
 */
class TestAdminAlertCommand extends Command
{
    protected $signature = 'admin:alert-test {severity=warning : One of info, warning, critical}';

    protected $description = 'Sends a test admin alert through every configured destination for a severity.';

    public function handle(AdminAlerts $alerts): int
    {
        $severity = AlertSeverity::tryFrom($this->argument('severity'));

        if ($severity === null) {
            $this->error('Severity must be one of: info, warning, critical.');

            return self::FAILURE;
        }

        if (! config('admin_alerts.enabled')) {
            $this->warn('Admin alerts are disabled (ADMIN_ALERTS_ENABLED=false). Nothing was sent.');

            return self::FAILURE;
        }

        $sent = $alerts->raise(
            key: 'admin.alert_test',
            title: 'Test admin alert',
            body: 'Delivery check triggered by the admin:alert-test command. No action required.',
            context: [
                'severity' => $severity->label(),
                'environment' => config('app.env'),
                'triggered_at' => now()->toIso8601String(),
            ],
            severity: $severity,
            throttleKey: 'admin.alert_test:'.Str::random(16),
        );

        if (! $sent) {
            $this->error("No destination is configured for [{$severity->value}] alerts. Check admin_alerts.routing and the ADMIN_ALERT_* environment values.");

            return self::FAILURE;
        }

        $this->info("Test alert queued for [{$severity->value}] destinations.");

        return self::SUCCESS;
    }
}
