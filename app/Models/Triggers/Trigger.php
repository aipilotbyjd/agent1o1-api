<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\SubscriptionStatus;
use App\Enums\Triggers\TriggerDisabledReason;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Triggers\TriggerDisabledNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Timezones\Timezones;
use App\Services\Triggers\Push\PushSources;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Database\Factories\Triggers\TriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Something that starts a workflow or an agent automatically: a webhook URL,
 * a schedule, a poll, or "Run now".
 *
 * A workflow trigger is usually owned by a trigger node on the canvas
 * (`trigger_node_key`) and kept in step with the published graph by
 * TriggerNodeSync. One with no node was made through the API: an agent
 * trigger, or a legacy workflow trigger.
 *
 * It also carries its own circuit breaker: enough failed or dropped events in
 * a row, or a storm of events in a minute, switch it off and tell its owners
 * (recordFailure(), recordSkip(), recordStorm()).
 */
#[Fillable([
    'workspace_id', 'target_type', 'target_id', 'type', 'preset_id', 'config',
    'is_active', 'credential_id', 'created_by',
])]
#[Hidden(['signing_secret'])]
class Trigger extends Model
{
    /** @use HasFactory<TriggerFactory> */
    use HasFactory, HasUuids;

    /**
     * Defaults for a new trigger: on, with clean failure and skip streaks.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'consecutive_failure_count' => 0,
        'consecutive_skip_count' => 0,
    ];

    /**
     * Keep a schedule trigger's `next_run_at` current.
     *
     * Recomputed when its cron, timezone or on/off state changes, or when it
     * has none yet. WorkspaceService recomputes it when the workspace timezone
     * it falls back to changes. RunDueTriggersCommand advances it after each firing. Any
     * other type has no next run.
     */
    protected static function booted(): void
    {
        static::saving(function (Trigger $trigger): void {
            if ($trigger->type !== TriggerType::Schedule) {
                $trigger->next_run_at = null;

                return;
            }

            $isSwitchedOn = $trigger->isDirty('is_active') && $trigger->is_active;

            if ($trigger->next_run_at === null || $trigger->isDirty(['config', 'type']) || $isSwitchedOn) {
                $trigger->next_run_at = $trigger->nextRun(now(), allowCurrentMinute: true);
            }
        });

        // A polling trigger made, switched on or off, or pointed at another
        // account or settings: bring its push subscription in line. Queued,
        // and only once the surrounding publish has committed.
        static::saved(function (Trigger $trigger): void {
            if (! collect((array) config('triggers.push'))->contains(fn (array $provider): bool => (bool) ($provider['enabled'] ?? false))) {
                return;
            }

            $changed = $trigger->wasRecentlyCreated || $trigger->wasChanged(['is_active', 'config', 'credential_id', 'type']);

            if ($trigger->type === TriggerType::Polling && $changed && app(PushSources::class)->anyEnabled()) {
                SyncTriggerSubscription::dispatch($trigger->id)->afterCommit();
            }
        });
    }

    /**
     * When this schedule is next due at or after `$from`, in UTC, reading a
     * cron with no timezone of its own in the workspace's.
     */
    public function nextRun(CarbonInterface $from, bool $allowCurrentMinute = false): ?Carbon
    {
        return self::nextRunFor($this->config ?? [], $from, $allowCurrentMinute, $this->workspace?->timezone ?? Timezones::DEFAULT);
    }

    /**
     * When a schedule config is next due at or after `$from`, in UTC.
     *
     * The cron is read in the config's timezone, or `$defaultTimezone` when it
     * has none or an unknown one. Returns null for a missing or invalid cron,
     * which is then never due.
     *
     * @param  array<string, mixed>  $config
     * @param  bool  $allowCurrentMinute  count `$from`'s own minute: true for a trigger just set up or switched on (one made at 09:00 for "0 9 * * *" fires now), false when moving past a slot that just fired
     */
    public static function nextRunFor(array $config, CarbonInterface $from, bool $allowCurrentMinute = false, string $defaultTimezone = Timezones::DEFAULT): ?Carbon
    {
        $timezone = Timezones::resolve($config['timezone'] ?? null, $defaultTimezone);

        if (filled($config['run_once_at'] ?? null)) {
            return self::onceRunFor((string) $config['run_once_at'], $from, $allowCurrentMinute, $timezone);
        }

        $cron = $config['cron'] ?? null;

        if (! is_string($cron) || ! CronExpression::isValidExpression($cron)) {
            return null;
        }

        $next = (new CronExpression($cron))->getNextRunDate($from->toDateTimeImmutable(), 0, $allowCurrentMinute, $timezone);

        return Carbon::instance($next)->utc()->startOfMinute();
    }

    /**
     * The one slot of a one-time schedule, in UTC, or null once it has passed
     * (or when the date can't be read).
     *
     * A date with no offset is read in `$timezone`.
     */
    private static function onceRunFor(string $runOnceAt, CarbonInterface $from, bool $allowCurrentMinute, string $timezone): ?Carbon
    {
        try {
            $slot = Carbon::parse($runOnceAt, $timezone)->utc()->startOfMinute();
        } catch (\Throwable) {
            return null;
        }

        $floor = Carbon::instance($from->toDateTimeImmutable())->utc()->startOfMinute();

        return ($allowCurrentMinute ? $slot->gte($floor) : $slot->gt($floor)) ? $slot : null;
    }

    /**
     * Whether this is a schedule that runs once and then switches itself off.
     */
    public function isOneTimeSchedule(): bool
    {
        return $this->type === TriggerType::Schedule && filled($this->config['run_once_at'] ?? null);
    }

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'config' => 'array',
            'signing_secret' => 'encrypted',
            'is_active' => 'boolean',
            'poll_cursor' => 'array',
            'consecutive_failure_count' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'next_poll_at' => 'datetime',
            'consecutive_skip_count' => 'integer',
        ];
    }

    /**
     * The trigger's push subscription, when it has one.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(TriggerSubscription::class);
    }

    /**
     * Minutes between polls.
     *
     * The trigger's own interval, never below the platform minimum. A trigger
     * whose push subscription is receiving events only polls to reconcile
     * (`triggers.push_reconcile_poll_minutes`), in case an event was missed.
     */
    public function pollIntervalMinutes(): int
    {
        $interval = max(
            (int) config('triggers.min_poll_interval_minutes'),
            (int) ($this->config['poll_interval_minutes'] ?? config('triggers.poll_every_minutes')),
            $this->workspace?->currentPlan()?->minPollIntervalMinutes() ?? 1,
        );

        if ($this->subscription?->status === SubscriptionStatus::Active) {
            return max($interval, (int) config('triggers.push_reconcile_poll_minutes'));
        }

        return $interval;
    }

    /**
     * When the next poll is due: one interval from now, spread by up to 10 %
     * so polls set up together don't all land on the same minute.
     *
     * A trigger without working push that keeps finding nothing backs off
     * (see backoffMultiplier), so a quiet Tasks list isn't read every few
     * minutes all day.
     *
     * @param  int  $idlePolls  how many polls in a row found nothing new
     */
    public function nextPollTime(int $idlePolls = 0): Carbon
    {
        $seconds = $this->pollIntervalMinutes() * 60 * $this->backoffMultiplier($idlePolls);

        return now()->addSeconds($seconds + random_int(0, (int) ($seconds / 10)));
    }

    /**
     * How many times longer than its interval a quiet trigger waits: one more
     * interval for every `triggers.adaptive_poll.after_empty_polls` empty
     * polls, up to `max_multiplier`. Never for a trigger with working push
     * (it already polls only to reconcile) or when the switch is off.
     */
    public function backoffMultiplier(int $idlePolls): int
    {
        $adaptive = (array) config('triggers.adaptive_poll');

        if (! ($adaptive['enabled'] ?? false) || $this->subscription?->status === SubscriptionStatus::Active) {
            return 1;
        }

        $perStep = max(1, (int) ($adaptive['after_empty_polls'] ?? 6));

        return min(max(1, (int) ($adaptive['max_multiplier'] ?? 4)), 1 + intdiv(max(0, $idlePolls), $perStep));
    }

    /**
     * The person a run or poll acts as: whoever set the trigger up while they
     * are still in the workspace, otherwise the workspace's owner, otherwise
     * one of its admins.
     *
     * Without the fallback, a trigger whose creator left the team would run
     * with nobody behind it, and its polls would fail until the circuit
     * breaker switched it off.
     */
    public function actingUser(): ?User
    {
        $creator = $this->creator;

        if ($creator !== null && $this->workspace->users()
            ->whereKey($creator->getKey())
            ->whereNull((new WorkspaceMember)->qualifyColumn('deleted_at'))
            ->exists()) {
            return $creator;
        }

        return $this->workspace->owner
            ?? app(NotificationDispatcher::class)->ownersAndAdmins($this->workspace)->first();
    }

    /**
     * The workspace the trigger belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The provider preset it was made from, if any (API triggers only).
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(TriggerPreset::class, 'preset_id');
    }

    /**
     * The workflow or agent it starts.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who set it up. Runs it starts are attributed to them, and they decide
     * which personal accounts it may use.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Everything that has arrived for it, newest last.
     */
    public function events(): HasMany
    {
        return $this->hasMany(TriggerEvent::class);
    }

    /**
     * A fresh 64-character URL token for a webhook trigger.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Count one more event in a row that failed to start a run, and switch
     * the trigger off once that reaches maxFailures().
     *
     * Called once per event, when its job gives up, not once per retry.
     */
    public function recordFailure(): void
    {
        $this->increment('consecutive_failure_count');

        if ($this->consecutive_failure_count >= $this->maxFailures()) {
            $this->switchOff(TriggerDisabledReason::Failures);
        }
    }

    /**
     * Count one more event in a row that was dropped without running, and
     * switch the trigger off once that reaches `triggers.skips_before_disable`.
     *
     * A drop (out of credits, or the last run still going under the "skip"
     * overlap policy) isn't the trigger's fault, so it doesn't count as a
     * failure. A long unbroken run of them still means the trigger fires far
     * more often than the workspace can run it.
     */
    public function recordSkip(): void
    {
        $this->increment('consecutive_skip_count');

        if ($this->consecutive_skip_count >= (int) config('triggers.skips_before_disable')) {
            $this->switchOff(TriggerDisabledReason::Skips);
        }
    }

    /**
     * Switch the trigger off because events arrived far faster than it can
     * sensibly run (see TriggerService::tripsStormDetector()).
     */
    public function recordStorm(): void
    {
        $this->switchOff(TriggerDisabledReason::Storm);
    }

    /**
     * Events in one minute above which the trigger switches itself off: its
     * own `config.storm_limit` (clamped to 10 to 10,000), else the default.
     */
    public function stormLimit(): int
    {
        $configured = $this->config['storm_limit'] ?? null;

        return is_numeric($configured)
            ? max(10, min(10000, (int) $configured))
            : (int) config('triggers.storm.max_events_per_minute');
    }

    /**
     * Failures in a row before the trigger switches itself off: its own
     * `config.max_failures` (clamped to the allowed range), else the default.
     */
    public function maxFailures(): int
    {
        [$min, $max] = config('triggers.max_failures_range');
        $configured = $this->config['max_failures'] ?? null;

        return is_numeric($configured)
            ? max((int) $min, min((int) $max, (int) $configured))
            : (int) config('triggers.failures_before_disable');
    }

    /**
     * Switch the trigger off and tell its owners why.
     *
     * Does nothing if it's already off, so a streak that keeps growing
     * doesn't repeat the message.
     */
    private function switchOff(TriggerDisabledReason $reason): void
    {
        if (! $this->is_active) {
            return;
        }

        $this->forceFill(['is_active' => false, 'disabled_reason' => $reason->value])->save();

        $this->notifyDisabled($reason);
    }

    /**
     * Notify the workspace's owners and admins, and whoever set the trigger
     * up, that it was switched off.
     */
    private function notifyDisabled(TriggerDisabledReason $reason): void
    {
        $dispatcher = app(NotificationDispatcher::class);

        $recipients = $dispatcher->ownersAndAdmins($this->workspace)
            ->push($this->creator)
            ->filter()
            ->unique('id')
            ->values();

        $dispatcher->dispatch($recipients, new TriggerDisabledNotification($this, $reason));
    }

    /**
     * Record that an event started a run: clears both streaks and stamps
     * `last_run_at`.
     */
    public function recordSuccess(): void
    {
        // forceFill: these are system bookkeeping, deliberately not fillable,
        // so update() would silently drop them.
        $this->forceFill(['consecutive_failure_count' => 0, 'consecutive_skip_count' => 0, 'last_run_at' => now()])->save();
    }
}
