<?php

namespace App\Services\SuperAdmin\Alerts;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\Evaluators\AdminSecurity;
use App\Services\SuperAdmin\Alerts\Evaluators\AuditChainBroken;
use App\Services\SuperAdmin\Alerts\Evaluators\FailedJobsGrowth;
use App\Services\SuperAdmin\Alerts\Evaluators\FailedRunsSpike;
use App\Services\SuperAdmin\Alerts\Evaluators\PaymentFailures;
use App\Services\SuperAdmin\Alerts\Evaluators\ProviderPaused;
use App\Services\SuperAdmin\Alerts\Evaluators\QueueBacklog;
use App\Services\SuperAdmin\Alerts\Evaluators\SchedulerDead;
use App\Services\SuperAdmin\Alerts\Evaluators\ServiceDown;
use App\Services\SuperAdmin\Alerts\Evaluators\SignupAnomaly;
use App\Services\SuperAdmin\Alerts\Evaluators\WebhookFailure;
use App\Services\SuperAdmin\Alerts\Evaluators\WorkspaceSpendSpike;

/**
 * The built-in alert rules and the evaluator behind each type. `sync()` creates
 * any rule that is missing and never touches one that exists, so tuned
 * thresholds survive a deploy. Adding a rule type is one evaluator class and
 * one line in each list here.
 */
class AlertRuleCatalog
{
    /**
     * @var list<class-string<AlertEvaluator>>
     */
    public const EVALUATORS = [
        FailedRunsSpike::class,
        WebhookFailure::class,
        QueueBacklog::class,
        SchedulerDead::class,
        FailedJobsGrowth::class,
        WorkspaceSpendSpike::class,
        PaymentFailures::class,
        SignupAnomaly::class,
        ServiceDown::class,
        AdminSecurity::class,
        AuditChainBroken::class,
        ProviderPaused::class,
    ];

    /**
     * Name, severity and cooldown (minutes) of each built-in rule, by type.
     *
     * @var array<string, array{name: string, severity: AlertSeverity, cooldown: int}>
     */
    private const BUILT_IN = [
        'failed_runs_spike' => ['name' => 'Failed runs spike', 'severity' => AlertSeverity::Warning, 'cooldown' => 60],
        'webhook_failure' => ['name' => 'Stripe webhook failure', 'severity' => AlertSeverity::Warning, 'cooldown' => 120],
        'queue_backlog' => ['name' => 'Queue backlog', 'severity' => AlertSeverity::Warning, 'cooldown' => 30],
        'scheduler_dead' => ['name' => 'Scheduler not running', 'severity' => AlertSeverity::Critical, 'cooldown' => 30],
        'failed_jobs_growth' => ['name' => 'Failed jobs growing', 'severity' => AlertSeverity::Warning, 'cooldown' => 60],
        'workspace_spend_spike' => ['name' => 'Model spend spike', 'severity' => AlertSeverity::Warning, 'cooldown' => 360],
        'payment_failures' => ['name' => 'Payment failures', 'severity' => AlertSeverity::Warning, 'cooldown' => 360],
        'signup_anomaly' => ['name' => 'Signup anomaly', 'severity' => AlertSeverity::Warning, 'cooldown' => 120],
        'service_down' => ['name' => 'Service down', 'severity' => AlertSeverity::Critical, 'cooldown' => 30],
        'admin_security' => ['name' => 'Super Admin sign-in trouble', 'severity' => AlertSeverity::Warning, 'cooldown' => 60],
        'audit_chain_broken' => ['name' => 'Audit log integrity', 'severity' => AlertSeverity::Critical, 'cooldown' => 720],
        'provider_paused' => ['name' => 'Provider paused', 'severity' => AlertSeverity::Info, 'cooldown' => 1440],
    ];

    /**
     * The evaluator class for each rule type.
     *
     * @return array<string, class-string<AlertEvaluator>>
     */
    public function types(): array
    {
        $types = [];

        foreach (self::EVALUATORS as $class) {
            $types[$class::type()] = $class;
        }

        return $types;
    }

    /**
     * Built only when needed, so one evaluator whose dependencies cannot be
     * built (a missing table, a bad config) cannot take the others with it.
     */
    public function evaluatorFor(AlertRule $rule): ?AlertEvaluator
    {
        $class = $this->types()[$rule->type] ?? null;

        return $class === null ? null : app($class);
    }

    /**
     * Creates the built-in rules that do not exist yet.
     */
    public function sync(): void
    {
        foreach ($this->types() as $type => $class) {
            $definition = self::BUILT_IN[$type];

            AlertRule::query()->firstOrCreate(['key' => $type], [
                'name' => $definition['name'],
                'type' => $type,
                'config' => $class::defaults(),
                'severity' => $definition['severity'],
                'cooldown_minutes' => $definition['cooldown'],
                'enabled' => true,
            ]);
        }
    }

    /**
     * The settings a rule may be tuned with.
     *
     * @return list<string>
     */
    public function tunableKeys(AlertRule $rule): array
    {
        return [...array_keys($this->defaultsFor($rule)), 'escalate_after_minutes'];
    }

    /**
     * A rule's settings with the evaluator's defaults filled in under them.
     */
    public function withDefaults(AlertRule $rule): AlertRule
    {
        $rule->config = [...$this->defaultsFor($rule), ...($rule->config ?? [])];

        return $rule;
    }

    /**
     * @return array<string, int|float|bool>
     */
    private function defaultsFor(AlertRule $rule): array
    {
        $class = $this->types()[$rule->type] ?? null;

        return $class === null ? [] : $class::defaults();
    }
}
