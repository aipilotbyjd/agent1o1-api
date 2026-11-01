<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\Notifications\AlertSeverity;
use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Models\SuperAdmin\SuperAdminLoginEvent;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * Sign-in trouble on the Super Admin panel: a burst of failures or lockouts,
 * or a refresh token being reused (a stolen session). A new IP, a 2FA reset
 * and role changes already alert immediately through `SuperAdminAlerter`.
 */
class AdminSecurity implements AlertEvaluator
{
    public static function type(): string
    {
        return 'admin_security';
    }

    public static function defaults(): array
    {
        return ['window_minutes' => 15, 'failed_logins' => 5];
    }

    public function evaluate(AlertRule $rule): array
    {
        $since = now()->subMinutes((int) $rule->setting('window_minutes'));

        $counts = SuperAdminLoginEvent::query()
            ->where('created_at', '>=', $since)
            ->whereIn('event', [
                SuperAdminAuthEvent::LoginFailed->value,
                SuperAdminAuthEvent::TwoFactorFailed->value,
                SuperAdminAuthEvent::LockedOut->value,
                SuperAdminAuthEvent::IpBlocked->value,
                SuperAdminAuthEvent::RefreshReuseDetected->value,
            ])
            ->selectRaw('event, count(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        $signals = [];
        $failures = (int) $counts->except(SuperAdminAuthEvent::RefreshReuseDetected->value)->sum();

        if ($failures >= (int) $rule->setting('failed_logins')) {
            $signals[] = new AlertSignal('failed-logins', "{$failures} failed sign-in attempts on the Super Admin panel in the last {$rule->setting('window_minutes')} minutes.", $counts->all());
        }

        if (($counts[SuperAdminAuthEvent::RefreshReuseDetected->value] ?? 0) > 0) {
            $signals[] = new AlertSignal('token-reuse', 'A Super Admin refresh token was reused: a session may have been stolen.', ['events' => (int) $counts[SuperAdminAuthEvent::RefreshReuseDetected->value]], AlertSeverity::Critical);
        }

        return $signals;
    }
}
