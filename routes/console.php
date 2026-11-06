<?php

use App\Jobs\System\ExpireStaleWaitsJob;
use App\Jobs\System\FailStuckAgentTurnsJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Every command runs on one server and never overlaps itself. onOneServer()
| requires a shared cache store (Redis or database) in production.
|
*/

Schedule::onOneServer()->withoutOverlapping()->group(function (): void {
    // Workflow triggers
    Schedule::command('triggers:run-due')->everyMinute();
    Schedule::command('triggers:retry-stuck')->everyFiveMinutes();
    Schedule::command('triggers:flush-batches')->everyMinute();
    Schedule::command('triggers:renew-watches')->everyTenMinutes();
    Schedule::command('triggers:collect-watches')->hourly();
    Schedule::command('triggers:prune-events')->dailyAt((string) config('triggers.prune_at'));

    // Agents
    Schedule::command('agents:expire-actions')->everyMinute();
    Schedule::command('reflections:run-due')->everyFifteenMinutes();

    // Assistant
    Schedule::command('assistant:expire-actions')->everyMinute();
    Schedule::command('assistant:run-due-briefings')->everyMinute();
    Schedule::command('assistant:run-due-triggers')->everyMinute();
    Schedule::command('assistant:check-inboxes')->everyTwoMinutes();
    Schedule::command('assistant:check-follow-ups')->everyFifteenMinutes();
    Schedule::command('assistant:sync-meetings')->everyTenMinutes();

    // Connectors, knowledge & skills
    Schedule::command('connectors:refresh-expiring')->everyFiveMinutes();
    Schedule::command('connectors:check-health')->hourly();
    Schedule::command('ai-credentials:check')->hourly();
    Schedule::command('ai-credentials:refresh-tokens')->everyFiveMinutes();
    Schedule::command('knowledge:sync-sources')->everyFifteenMinutes();
    Schedule::command('skills:sync-sources')->everyFifteenMinutes();

    // Super Admin
    Schedule::command('super-admin:audit-verify')->dailyAt('05:00')->when(fn (): bool => (bool) config('super_admin.enabled'));

    // System health, alerts & revenue
    Schedule::command('system:heartbeat')->everyMinute();
    Schedule::command('system:health-snapshot')->everyFiveMinutes()->when(fn (): bool => (bool) config('super_admin.enabled'));
    Schedule::command('alerts:evaluate')->everyMinute()->when(fn (): bool => (bool) config('super_admin.enabled'));
    Schedule::command('alerts:digest')->dailyAt('07:30')->when(fn (): bool => (bool) config('super_admin.enabled'));
    Schedule::command('alerts:prune')->dailyAt('05:30')->when(fn (): bool => (bool) config('super_admin.enabled'));
    Schedule::command('revenue:snapshot')->dailyAt('00:10')->when(fn (): bool => (bool) config('super_admin.enabled'));

    // Broadcasts
    Schedule::command('broadcasts:dispatch-due')->everyMinute();

    // Models
    Schedule::command('model-catalog:retire-due')->dailyAt('04:00');
    Schedule::command('model-usage:rollup')->hourlyAt(5);
    Schedule::command('model-spend:check')->hourlyAt(15);
    Schedule::command('model-prices:sync')->dailyAt('03:40')->when(fn (): bool => (bool) config('model_operations.price_sync.enabled'));
    Schedule::command('ai-calls:prune')->dailyAt('04:20');

    // Admin intelligence
    Schedule::command('intelligence:rollup')->hourlyAt(10);
    Schedule::command('intelligence:recommend')->hourlyAt(35);
    Schedule::command('intelligence:predict')->dailyAt('01:15');
    Schedule::command('intelligence:briefing')->weeklyOn(1, '07:45')->when(fn (): bool => (bool) config('intelligence.briefing.enabled'));

    // Workflow builder
    Schedule::command('workflow-builder:archive-idle')->dailyAt('03:00');

    // Billing
    Schedule::command('billing:expire-trials')->hourly();
    Schedule::command('billing:expire-plan-grants')->hourly();
    Schedule::command('billing:notify-trial-ending')->daily();
    Schedule::command('billing:invoice-overage')->daily();
    Schedule::command('credits:expire')->hourlyAt(20);
    Schedule::command('credits:prune-reservations')->hourlyAt(25);

    // Referrals
    Schedule::command('referrals:grant-pending')->hourly();
    Schedule::command('referrals:notify-plan-time-ending')->daily();
    Schedule::command('referrals:admin-digest')->dailyAt('08:00');
    Schedule::command('referrals:prune-visits')->weekly();
});

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
|
| Queued maintenance jobs. They only dispatch, so overlap locks aren't needed.
|
*/

Schedule::job(new ExpireStaleWaitsJob)->everyMinute()->onOneServer();
Schedule::job(new FailStuckAgentTurnsJob)->everyFiveMinutes()->onOneServer();
