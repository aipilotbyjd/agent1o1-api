<?php

namespace App\Services\SuperAdmin\Alerts;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns what the evaluators find into alert events and notifications.
 *
 * Per rule and subject (`fingerprint`) there is at most one active event: a
 * condition that stays true updates it rather than raising a new alert. It
 * notifies when the event is new, again once the rule's cooldown has passed,
 * and once more, louder, if nobody acknowledges it within
 * `escalate_after_minutes`. When the condition clears the event resolves
 * itself and a recovery notice is sent. Several notices from one run are
 * grouped into one digest message.
 *
 * A rule that throws never stops the others: it is reported, and a critical
 * "monitoring failed" alert is raised so a broken rule cannot silently turn
 * alerting off.
 */
class AlertEngine
{
    /**
     * @var list<array{kind: string, event: AlertEvent}>
     */
    private array $outbox = [];

    public function __construct(
        private readonly AlertRuleCatalog $catalog,
        private readonly AdminAlerts $alerts,
    ) {}

    /**
     * @return array{rules: int, fired: int, resolved: int, failed: int, notified: int}
     */
    public function run(): array
    {
        $this->catalog->sync();
        $this->outbox = [];

        $summary = ['rules' => 0, 'fired' => 0, 'resolved' => 0, 'failed' => 0, 'notified' => 0];

        foreach (AlertRule::query()->where('enabled', true)->orderBy('key')->get() as $rule) {
            if (! isset($this->catalog->types()[$rule->type])) {
                continue;
            }

            $summary['rules']++;

            try {
                $evaluator = $this->catalog->evaluatorFor($rule);
                $this->catalog->withDefaults($rule);
                $result = $this->reconcile($rule, $evaluator->evaluate($rule));
                $summary['fired'] += $result['fired'];
                $summary['resolved'] += $result['resolved'];
            } catch (Throwable $e) {
                report($e);
                $summary['failed']++;
                $this->monitoringFailed($rule, $e);
            }
        }

        $summary['notified'] = $this->flush();

        return $summary;
    }

    /**
     * Sends a test notification for the rule so delivery can be verified.
     */
    public function sendTest(AlertRule $rule): bool
    {
        return $this->alerts->raise(
            "super-admin.alert.{$rule->key}",
            "Test alert: {$rule->name}",
            'This is a test of the alert rule. Nothing is wrong.',
            ['rule' => $rule->key, 'test' => true],
            $rule->severity,
            throttleKey: 'alert-test:'.$rule->key.':'.now()->getTimestamp(),
        );
    }

    /**
     * @param  list<AlertSignal>  $signals
     * @return array{fired: int, resolved: int}
     */
    private function reconcile(AlertRule $rule, array $signals): array
    {
        $active = AlertEvent::query()->active()->where('alert_rule_id', $rule->id)->get()->keyBy('fingerprint');
        $seen = [];
        $fired = 0;

        foreach ($signals as $signal) {
            $seen[$signal->fingerprint] = true;
            $severity = $signal->severity ?? $rule->severity;
            $event = $active->get($signal->fingerprint);

            if ($event === null) {
                $event = AlertEvent::query()->create([
                    'alert_rule_id' => $rule->id,
                    'fingerprint' => $signal->fingerprint,
                    'severity' => $severity,
                    'status' => AlertEvent::STATUS_OPEN,
                    'title' => $signal->title,
                    'context' => $signal->context,
                    'first_fired_at' => now(),
                    'last_fired_at' => now(),
                    'occurrences' => 1,
                ]);
                $event->setRelation('rule', $rule);

                $this->queue($rule, 'new', $event);
                $fired++;

                continue;
            }

            $event->forceFill([
                'title' => $signal->title,
                'context' => $signal->context,
                'severity' => $severity,
                'last_fired_at' => now(),
                'occurrences' => $event->occurrences + 1,
            ])->save();
            $event->setRelation('rule', $rule);

            $this->considerRepeat($rule, $event);
        }

        $resolved = 0;

        foreach ($active as $fingerprint => $event) {
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $event->forceFill(['status' => AlertEvent::STATUS_RESOLVED, 'resolved_at' => now()])->save();
            $event->setRelation('rule', $rule);
            $resolved++;

            if ($event->last_notified_at !== null) {
                $this->queue($rule, 'recovered', $event);
            }
        }

        return ['fired' => $fired, 'resolved' => $resolved];
    }

    private function considerRepeat(AlertRule $rule, AlertEvent $event): void
    {
        if ($event->status !== AlertEvent::STATUS_OPEN) {
            return;
        }

        $escalateAfter = (int) $rule->setting('escalate_after_minutes', config('super_admin_alerts.engine.escalate_after_minutes'));

        if ($escalateAfter > 0 && $event->escalated_at === null && $event->severity !== AlertSeverity::Critical
            && $event->first_fired_at->lte(now()->subMinutes($escalateAfter))) {
            $this->queue($rule, 'escalated', $event);

            return;
        }

        $cooldown = $event->last_notified_at?->copy()->addMinutes($rule->cooldown_minutes);

        if ($cooldown === null || $cooldown->lte(now())) {
            $this->queue($rule, 'repeat', $event);
        }
    }

    private function queue(AlertRule $rule, string $kind, AlertEvent $event): void
    {
        if ($rule->isSnoozed()) {
            return;
        }

        $loud = $event->severity === AlertSeverity::Critical || $kind === 'escalated';

        if (! $loud && $this->inQuietHours()) {
            return;
        }

        if ($kind === 'escalated') {
            $event->forceFill(['escalated_at' => now()])->save();
        }

        $this->outbox[] = ['kind' => $kind, 'event' => $event];
    }

    /**
     * @return int how many notifications went out (a digest counts as one)
     */
    private function flush(): int
    {
        if ($this->outbox === []) {
            return 0;
        }

        $outbox = $this->outbox;
        $this->outbox = [];

        if (count($outbox) > (int) config('super_admin_alerts.engine.digest_threshold')) {
            $this->sendDigest($outbox);

            return 1;
        }

        foreach ($outbox as $item) {
            $this->send($item['kind'], $item['event']);
        }

        return count($outbox);
    }

    private function send(string $kind, AlertEvent $event): void
    {
        $severity = match ($kind) {
            'recovered' => AlertSeverity::Info,
            'escalated' => AlertSeverity::Critical,
            default => $event->severity,
        };

        $title = match ($kind) {
            'recovered' => "Resolved: {$event->title}",
            'escalated' => "Unacknowledged: {$event->title}",
            default => $event->title,
        };

        $this->alerts->raise(
            "super-admin.alert.{$event->rule->key}",
            $title,
            null,
            [...($event->context ?? []), 'rule' => $event->rule->name, 'occurrences' => $event->occurrences],
            $severity,
            throttleKey: "alert-event:{$event->id}:{$kind}:".now()->getTimestamp(),
        );

        $event->forceFill(['last_notified_at' => now()])->save();
    }

    /**
     * @param  list<array{kind: string, event: AlertEvent}>  $outbox
     */
    private function sendDigest(array $outbox): void
    {
        $lines = array_map(fn (array $item): string => "[{$item['kind']}] {$item['event']->title}", array_slice($outbox, 0, 25));

        $worst = collect($outbox)->contains(fn (array $item): bool => $item['event']->severity === AlertSeverity::Critical && $item['kind'] !== 'recovered')
            ? AlertSeverity::Critical
            : AlertSeverity::Warning;

        $this->alerts->raise(
            'super-admin.alert.digest',
            count($outbox).' alerts need attention',
            implode("\n", $lines),
            ['alerts' => count($outbox)],
            $worst,
            throttleKey: 'alert-digest:'.now()->getTimestamp(),
        );

        foreach ($outbox as $item) {
            $item['event']->forceFill(['last_notified_at' => now()])->save();
        }
    }

    private function monitoringFailed(AlertRule $rule, Throwable $e): void
    {
        $this->alerts->raise(
            'super-admin.alert.monitoring_failed',
            "Alert rule \"{$rule->name}\" could not be evaluated",
            str($e->getMessage())->limit(300, '')->toString(),
            ['rule' => $rule->key, 'exception' => $e::class],
            AlertSeverity::Critical,
            throttleKey: 'alert-monitoring-failed:'.$rule->key,
        );
    }

    private function inQuietHours(): bool
    {
        $quiet = config('super_admin_alerts.engine.quiet_hours');

        if (! ($quiet['enabled'] ?? false)) {
            return false;
        }

        $zone = $quiet['timezone'] ?? 'UTC';
        $now = Carbon::now($zone);
        $start = Carbon::parse($quiet['start'], $zone)->setDateFrom($now);
        $end = Carbon::parse($quiet['end'], $zone)->setDateFrom($now);

        return $start->lte($end) ? $now->between($start, $end) : ($now->gte($start) || $now->lte($end));
    }
}
