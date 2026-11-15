<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskCadence;
use App\Enums\Operations\TaskKind;
use App\Models\Operations\OperationsTask;

/**
 * The built-in checklist. `sync()` adds any task that is missing, by its key,
 * and never touches one that exists, so a reworded, re-timed or switched-off
 * task stays as someone left it. Run it after a deploy that adds new tasks.
 */
class TaskCatalog
{
    /**
     * @return list<array{key: string, title: string, description: string, cadence: TaskCadence, kind: TaskKind, check?: string, target?: string}>
     */
    public function definitions(): array
    {
        $manual = fn (string $key, TaskCadence $cadence, string $title, string $description, ?string $target = null): array => compact('key', 'title', 'description', 'cadence', 'target') + ['kind' => TaskKind::Manual];
        $auto = fn (string $key, string $title, string $description, string $check, ?string $target = null): array => ['key' => $key, 'title' => $title, 'description' => $description, 'cadence' => TaskCadence::Daily, 'kind' => TaskKind::Auto, 'check' => $check, 'target' => $target];

        return [
            // Setup: once.
            $manual('setup.admin_team', TaskCadence::Once, 'Set up the admin team', 'Create one account per person with the lowest role they need, turn on two-factor, and disable the demo account.', 'admin-team'),
            $manual('setup.preflight', TaskCadence::Once, 'Run the go-live check', 'Run "php artisan admin:preflight" on the admin server and fix anything it flags.'),
            $manual('setup.backups', TaskCadence::Once, 'Turn backups on and check the first one', 'Set the storage bucket and nightly time, then confirm the first backup completes.', 'backups'),
            $manual('setup.restore_test', TaskCadence::Once, 'Practise restoring a backup', 'Follow the backup restore runbook once so you know it works before you need it.', 'backups'),
            $manual('setup.alert_rules', TaskCadence::Once, 'Review the alert rules', 'Tune the default rules and confirm where alerts and the daily digest are delivered.', 'alerts'),
            $manual('setup.stripe', TaskCadence::Once, 'Connect the Stripe webhook', 'Add the webhook endpoint in Stripe and check that no events fail.', 'billing-events'),
            $manual('setup.ai_providers', TaskCadence::Once, 'Add AI provider keys and spend controls', 'Add the provider keys, check prices, and set limits so costs cannot run away.', 'ai-models'),

            // Daily: checked by the platform.
            $auto('daily.scheduler', 'Scheduler is running', 'The scheduler runs every minute and drives alerts, backups and billing.', 'scheduler_heartbeat', 'system'),
            $auto('daily.health', 'Every service is healthy', 'The latest system health snapshot.', 'system_health', 'system'),
            $auto('daily.backups', 'Last backup succeeded', 'A backup should finish at least once a day.', 'backups', 'backups'),
            $auto('daily.failed_jobs', 'No failed jobs', 'Failed background jobs waiting to be retried or cleared.', 'failed_jobs', 'system'),
            $auto('daily.stuck_work', 'Nothing is stuck', 'Runs, agent turns and waits that have taken too long.', 'stuck_work', 'system'),
            $auto('daily.alerts', 'No open alerts', 'Alerts that are open or acknowledged but not resolved.', 'open_alerts', 'alerts'),
            $auto('daily.stripe_webhooks', 'No failing Stripe events', 'Stripe events that failed and need replaying.', 'stripe_webhooks', 'billing-events'),
            $auto('daily.audit_chain', 'Audit log is intact', 'The nightly integrity check of the audit log.', 'audit_chain', 'audit'),
            $auto('daily.maintenance', 'App is open for changes', 'Warns while a read-only maintenance window is open, so it is not left on.', 'maintenance_window', 'maintenance'),

            // Daily: by a person.
            $manual('daily.dashboard', TaskCadence::Daily, 'Glance at the dashboard', 'Look for anything unusual in sign-ups, runs and revenue.', 'dashboard'),
            $manual('daily.runs', TaskCadence::Daily, 'Check failed runs', 'Look at the failure rate and any sudden spike.', 'runs'),

            // Weekly.
            $manual('weekly.revenue', TaskCadence::Weekly, 'Review revenue and growth', 'MRR, churn, failed payments and trials that are ending.', 'revenue'),
            $manual('weekly.margins', TaskCadence::Weekly, 'Check AI margins', 'Make sure no customer or model is losing money.', 'unit-economics'),
            $manual('weekly.recommendations', TaskCadence::Weekly, 'Read the recommendations', 'Act on or dismiss what the analyst suggests.', 'recommendations'),
            $manual('weekly.audit', TaskCadence::Weekly, 'Skim the audit log', 'Look at privileged actions for anything you do not recognise.', 'audit'),
            $manual('weekly.referrals', TaskCadence::Weekly, 'Approve pending referral rewards', 'Approve or reject rewards waiting for a decision.', 'referrals'),
            $manual('weekly.admin_team', TaskCadence::Weekly, 'Review the admin team', 'Remove people who left and look for sessions you do not recognise.', 'admin-team'),
            $manual('weekly.catalog', TaskCadence::Weekly, 'Check connector health', 'Look for connectors or nodes that are failing.', 'catalog'),
            $manual('weekly.broadcasts', TaskCadence::Weekly, 'Check scheduled broadcasts', 'Look over campaigns and announcements before they go out.', 'broadcasts'),

            // Monthly.
            $manual('monthly.restore_test', TaskCadence::Monthly, 'Test a backup restore', 'Restore a recent backup into a throwaway environment.', 'backups'),
            $manual('monthly.access', TaskCadence::Monthly, 'Review everyone\'s access', 'Go through each admin\'s role and remove what is no longer needed.', 'admin-team'),
            $manual('monthly.alert_rules', TaskCadence::Monthly, 'Tune the alert rules', 'Retire rules that never fire or fire too often.', 'alerts'),
            $manual('monthly.prices', TaskCadence::Monthly, 'Review prices and plans', 'Check model prices, plans and credit packs are still right.', 'pricing'),
            $manual('monthly.updates', TaskCadence::Monthly, 'Apply updates and migrations', 'Update dependencies and run any pending migrations on a schedule you choose.'),
        ];
    }

    /**
     * Adds the tasks that are missing. Returns how many it added.
     */
    public function sync(): int
    {
        $existing = OperationsTask::query()->whereNotNull('key')->pluck('key')->all();
        $added = 0;

        foreach ($this->definitions() as $index => $definition) {
            if (in_array($definition['key'], $existing, true)) {
                continue;
            }

            OperationsTask::query()->create([
                ...$definition,
                'target' => $definition['target'] ?? null,
                'due_after_hours' => $definition['cadence']->defaultDueAfterHours(),
                'sort_order' => ($index + 1) * 10,
                'enabled' => true,
                'is_system' => true,
            ]);

            $added++;
        }

        return $added;
    }
}
