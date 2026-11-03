<?php

namespace App\Services\Triggers;

use App\Contracts\Triggers\RunStarter;
use App\Enums\Billing\PlanLimit;
use App\Enums\RunStatus;
use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\UsageBlockedException;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditGate;
use App\Services\Billing\PlanLimitGate;
use App\Services\Workflows\ConditionEvaluator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates and manages triggers, and runs the two halves of every firing.
 *
 * - receive() is the fast intake path that every source calls (webhook,
 *   schedule, poll, "Run now"): a few indexed queries and one insert, never
 *   a model call. It decides whether an event runs and records it.
 * - fire() is the slow path a queued FireTriggerEvent job runs afterwards:
 *   it starts the workflow or agent.
 *
 * @see docs/TRIGGERS_PLAN.md "Core pipeline"
 */
class TriggerService
{
    public function __construct(
        private readonly RunStarter $runStarter,
        private readonly CreditGate $creditGate,
        private readonly PlanLimitGate $limits,
    ) {}

    /**
     * Create a trigger through the API (a canvas trigger is created by
     * publishing instead). A webhook gets its URL token here.
     *
     * @param  array{preset_id?: string, config?: array<string, mixed>, is_active?: bool, credential_id?: string, signing_secret?: string}  $data
     *
     * @throws PlanLimitExceededException when switching it on would exceed the plan's active triggers
     */
    public function create(
        Workspace $workspace,
        TriggerTargetType $targetType,
        string $targetId,
        TriggerType $type,
        array $data,
        ?User $createdBy = null,
    ): Trigger {
        if ($data['is_active'] ?? true) {
            $this->limits->assertCanCreate($workspace, PlanLimit::ActiveTriggers);
        }

        $trigger = Trigger::query()->create([
            'workspace_id' => $workspace->id,
            'target_type' => $targetType->value,
            'target_id' => $targetId,
            'type' => $type,
            'preset_id' => $data['preset_id'] ?? null,
            'config' => $data['config'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'credential_id' => $data['credential_id'] ?? null,
            'created_by' => $createdBy?->id,
        ]);

        if ($type->usesToken()) {
            $trigger->forceFill(['token' => Trigger::generateToken()])->save();
        }

        if (isset($data['signing_secret'])) {
            $trigger->forceFill(['signing_secret' => $data['signing_secret']])->save();
        }

        return $trigger;
    }

    /**
     * Update a trigger's config, on/off state, account or signing secret.
     *
     * Switching it back on clears both streaks, or a single failure after the
     * circuit breaker tripped would trip it again. An empty or null
     * `signing_secret` removes the secret.
     *
     * @param  array{config?: array<string, mixed>, is_active?: bool, credential_id?: string|null, signing_secret?: string|null}  $data
     *
     * @throws PlanLimitExceededException when switching it on would exceed the plan's active triggers
     */
    public function update(Trigger $trigger, array $data): Trigger
    {
        $isReactivating = ($data['is_active'] ?? false) === true && ! $trigger->is_active;

        if ($isReactivating) {
            $this->limits->assertCanCreate($trigger->workspace, PlanLimit::ActiveTriggers);
        }

        $config = $data['config'] ?? $trigger->config;

        // What makes a trigger an agent's own follow-up (`AgentFollowUps`) is
        // engine-managed and never accepted from a request, so an edit keeps it.
        if (is_array($trigger->config['follow_up'] ?? null) && is_array($config)) {
            $config['follow_up'] = $trigger->config['follow_up'];
        }

        $trigger->update([
            'config' => $config,
            'is_active' => $data['is_active'] ?? $trigger->is_active,
        ]);

        if ($isReactivating) {
            $trigger->forceFill(['consecutive_failure_count' => 0, 'consecutive_skip_count' => 0, 'disabled_reason' => null])->save();
        }

        if (array_key_exists('credential_id', $data) && $trigger->type->usesCredential()) {
            $trigger->forceFill(['credential_id' => $data['credential_id']])->save();
        }

        if (array_key_exists('signing_secret', $data)) {
            $trigger->forceFill(['signing_secret' => filled($data['signing_secret']) ? $data['signing_secret'] : null])->save();
        }

        return $trigger;
    }

    /**
     * Give a webhook trigger a new URL. The old one stops working at once.
     */
    public function rotateToken(Trigger $trigger): Trigger
    {
        abort_unless($trigger->type->usesToken(), 422, 'Only webhook triggers have a token to rotate.');

        $trigger->forceFill(['token' => Trigger::generateToken()])->save();

        return $trigger;
    }

    /**
     * Take in one event: decide whether it runs, record it, and queue it.
     *
     * Checked in order: the trigger is on, the delivery isn't a duplicate,
     * the filters match, the target can run, the workspace has credits, and
     * (for a trigger set to skip) no run is in flight. Whatever stops it is
     * recorded on the event; only an event that passes is queued.
     *
     * "Run now" answers "busy" in the controller before calling this.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function receive(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $deliveryId = null,
        ?string $payloadSnippet = null,
        array $headers = [],
    ): TriggerEvent {
        if (! $trigger->is_active) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, 'Trigger is inactive.');
        }

        if ($deliveryId !== null) {
            $duplicate = $this->duplicateOf($trigger, $deliveryId);

            if ($duplicate !== null) {
                return $duplicate;
            }
        }

        if (! $this->matchesConfiguredFilters($trigger, $payload, $headers)) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Ignored);
        }

        if (! $this->runStarter->canRun($trigger)) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, 'Target is not runnable.');
        }

        // A suspended workspace can't run anything, and that isn't the
        // trigger's fault either, so it is recorded without feeding the skip
        // streak that would switch the trigger off for good.
        if ($trigger->workspace->isSuspended()) {
            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, 'The workspace is suspended.');
        }

        // Dropped here, not left to fail in FireTriggerEvent: running out of
        // credits isn't the trigger's fault, so it counts toward the skip
        // streak rather than the failure breaker.
        if (! $this->hasCredits($trigger)) {
            return $this->skip($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, 'The workspace is out of credits.');
        }

        // "Run now" answers "busy" itself, so this is for automated sources only.
        if ($source !== TriggerType::Manual && $this->skipsWhileRunning($trigger) && $this->runStarter->isAlreadyRunning($trigger)) {
            return $this->skip($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, 'The previous run was still going.');
        }

        // A trigger set to group events, or to wait for quiet, keeps them until
        // its window is over (triggers:flush-batches runs them as one). "Run
        // now" is never held.
        $isHeld = $this->holdsEvents($trigger) && $source !== TriggerType::Manual;

        if (! $isHeld && $source !== TriggerType::Manual && $this->tripsStormDetector($trigger)) {
            $trigger->recordStorm();

            return $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, "Switched off: more than {$trigger->stormLimit()} events arrived in a minute.");
        }

        try {
            $event = TriggerEvent::query()->create([
                'trigger_id' => $trigger->id,
                'source' => $source,
                'status' => $isHeld ? TriggerEventStatus::Held : TriggerEventStatus::Queued,
                'payload' => $payload,
                'payload_snippet' => $payloadSnippet,
                'headers' => $this->allowedHeaders($headers),
                'delivery_id' => $deliveryId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same event won the race. The unique
            // index is the real dedupe guarantee; the check above is a shortcut.
            return $this->duplicateOf($trigger, $deliveryId) ?? throw new UniqueConstraintViolationException;
        }

        // The insert above commits on its own, so the row exists before the
        // job can run. If a caller ever wraps receive() in a transaction, add
        // ->afterCommit() here to keep that guarantee.
        if (! $isHeld) {
            FireTriggerEvent::dispatch($trigger, $event);
        }

        return $event;
    }

    /**
     * Whether the trigger keeps its events back to run them together: it
     * groups them for a window (`batch_seconds`) or waits for quiet
     * (`debounce_seconds`).
     */
    public function holdsEvents(Trigger $trigger): bool
    {
        return (int) ($trigger->config['batch_seconds'] ?? 0) > 0 || (int) ($trigger->config['debounce_seconds'] ?? 0) > 0;
    }

    /**
     * Whether a trigger's held events are due to run.
     *
     * - Grouping: the oldest held event has waited its `batch_seconds`.
     * - Waiting for quiet: the newest has waited `debounce_seconds` with
     *   nothing newer, or the oldest has waited the longest a trigger may be
     *   kept back (`triggers.debounce.max_wait_seconds`), so a source that
     *   never goes quiet still runs.
     * - Neither (the trigger stopped holding): anything held is due at once,
     *   so nothing is left waiting.
     */
    public function heldWindowIsOver(Trigger $trigger): bool
    {
        $held = $trigger->events()
            ->where('status', TriggerEventStatus::Held->value)
            ->selectRaw('min(created_at) as oldest, max(created_at) as newest')
            ->first();

        if ($held?->oldest === null) {
            return false;
        }

        $oldest = Carbon::parse($held->oldest);
        $debounce = max(0, (int) ($trigger->config['debounce_seconds'] ?? 0));

        if ($debounce > 0) {
            return Carbon::parse($held->newest)->lte(now()->subSeconds($debounce))
                || $oldest->lte(now()->subSeconds(max($debounce, (int) config('triggers.debounce.max_wait_seconds'))));
        }

        return $oldest->lte(now()->subSeconds(max(0, (int) ($trigger->config['batch_seconds'] ?? 0))));
    }

    /**
     * Count one more event for the trigger in the current minute and say
     * whether that is over its storm limit.
     *
     * A fixed one-minute counter in the cache: atomic, cheap, and shared by
     * every worker. Events that were not going to run anyway (filtered out,
     * duplicates) never reach it.
     */
    private function tripsStormDetector(Trigger $trigger): bool
    {
        if (! config('triggers.storm.enabled')) {
            return false;
        }

        $key = "triggers:storm:{$trigger->id}:".intdiv(now()->getTimestamp(), 60);

        Cache::add($key, 0, 120);

        return Cache::increment($key) > $trigger->stormLimit();
    }

    /**
     * Run a trigger's held events as one: a single event carrying all of their
     * payloads, which the held ones then point to as batched.
     *
     * At most 500 are taken at a time; the rest go in the next flush.
     */
    public function flushHeld(Trigger $trigger): ?TriggerEvent
    {
        $combined = DB::transaction(function () use ($trigger): ?TriggerEvent {
            $held = $trigger->events()
                ->where('status', TriggerEventStatus::Held->value)
                ->oldest()
                ->orderBy('id')
                ->limit(500)
                ->lockForUpdate()
                ->get();

            if ($held->isEmpty()) {
                return null;
            }

            $combined = TriggerEvent::query()->create([
                'trigger_id' => $trigger->id,
                'source' => $held->first()->source,
                'status' => TriggerEventStatus::Queued,
                'payload' => [
                    'count' => $held->count(),
                    'first_at' => $held->first()->created_at?->toIso8601String(),
                    'last_at' => $held->last()->created_at?->toIso8601String(),
                    'latest' => $held->last()->payload,
                    'items' => $held->pluck('payload')->all(),
                ],
                'delivery_id' => 'batch:'.Str::uuid(),
            ]);

            TriggerEvent::query()
                ->whereIn('id', $held->modelKeys())
                ->update(['status' => TriggerEventStatus::Batched->value, 'processed_at' => now()]);

            return $combined;
        });

        if ($combined !== null) {
            FireTriggerEvent::dispatch($trigger, $combined);
        }

        return $combined;
    }

    /**
     * Record an item a polling trigger's first poll found already there.
     *
     * Stored as ignored under its delivery id, so later polls treat it as
     * seen and never start a run for it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordBaseline(Trigger $trigger, array $payload, string $deliveryId): TriggerEvent
    {
        return $this->writeTerminal($trigger, TriggerType::Polling, $payload, $deliveryId, null, [], TriggerEventStatus::Ignored, 'Existed before the trigger was turned on.');
    }

    /**
     * Record a request that failed authentication.
     *
     * Stored without its delivery id. Otherwise anyone could block a genuine
     * delivery by guessing its id and sending a badly signed copy first.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function reject(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $payloadSnippet,
        array $headers,
        string $reason,
    ): TriggerEvent {
        return TriggerEvent::query()->create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'status' => TriggerEventStatus::Rejected,
            'payload' => $payload,
            'payload_snippet' => $payloadSnippet,
            'headers' => $this->allowedHeaders($headers),
            'delivery_id' => null,
            'error' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Start the target for a queued event and record that it fired.
     *
     * Exceptions are left to propagate: FireTriggerEvent's retries and
     * failure handling decide what happens next.
     */
    public function fire(Trigger $trigger, TriggerEvent $event): void
    {
        $runId = $this->runStarter->start($trigger, $event->payload ?? []);

        $event->markFired($runId);
        $trigger->recordSuccess();
    }

    /**
     * Count a failed event toward the trigger's circuit breaker.
     */
    public function recordFailure(Trigger $trigger): void
    {
        $trigger->recordFailure();
    }

    /**
     * Whether the trigger is on and its target can run right now.
     */
    public function canRun(Trigger $trigger): bool
    {
        return $trigger->is_active && $this->runStarter->canRun($trigger);
    }

    /**
     * Whether the target already has a run in flight.
     *
     * "Run now" asks this to tell a person "busy". Automated events are only
     * dropped for it when the trigger is set to skip.
     */
    public function isAlreadyRunning(Trigger $trigger): bool
    {
        return $this->runStarter->isAlreadyRunning($trigger);
    }

    /**
     * Whether starting another run now would go past a concurrency cap: the
     * trigger's own (`config.max_concurrent_runs`) or the workspace's
     * (`triggers.max_concurrent_runs_per_workspace`).
     *
     * An event over the cap isn't dropped: FireTriggerEvent puts it back on
     * the queue and tries again shortly, so a burst is spread out instead of
     * starting every run at once.
     */
    public function atConcurrencyLimit(Trigger $trigger): bool
    {
        $triggerCap = (int) ($trigger->config['max_concurrent_runs'] ?? 0);

        if ($triggerCap > 0 && $this->runStarter->activeRuns($trigger) >= $triggerCap) {
            return true;
        }

        $workspaceCap = (int) config('triggers.max_concurrent_runs_per_workspace');

        return $workspaceCap > 0 && Run::query()
            ->where('workspace_id', $trigger->workspace_id)
            ->whereNull('parent_run_id')
            ->whereIn('status', [RunStatus::Pending, RunStatus::Running])
            ->count() >= $workspaceCap;
    }

    /**
     * Whether the trigger drops events while a run is in flight
     * (`config.if_already_running` is `skip`; the default, `queue`, doesn't).
     */
    private function skipsWhileRunning(Trigger $trigger): bool
    {
        return ($trigger->config['if_already_running'] ?? 'queue') === 'skip';
    }

    /**
     * Whether the workspace can start another run.
     */
    private function hasCredits(Trigger $trigger): bool
    {
        try {
            $this->creditGate->assertCanStartRun($trigger->workspace);
        } catch (UsageBlockedException) {
            return false;
        }

        return true;
    }

    /**
     * Record an event as skipped and count it toward the skip streak.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function skip(Trigger $trigger, TriggerType $source, array $payload, ?string $deliveryId, ?string $payloadSnippet, array $headers, string $reason): TriggerEvent
    {
        $event = $this->writeTerminal($trigger, $source, $payload, $deliveryId, $payloadSnippet, $headers, TriggerEventStatus::Skipped, $reason);

        // A redelivery returns the earlier event; only a fresh drop counts.
        if ($event->wasRecentlyCreated) {
            $trigger->recordSkip();
        }

        return $event;
    }

    /**
     * The event already recorded under this delivery id, if any, with its
     * duplicate count bumped.
     */
    private function duplicateOf(Trigger $trigger, string $deliveryId): ?TriggerEvent
    {
        $existing = TriggerEvent::query()
            ->where('trigger_id', $trigger->id)
            ->where('delivery_id', $deliveryId)
            ->first();

        if ($existing === null) {
            return null;
        }

        $existing->increment('duplicate_count');

        return $existing;
    }

    /**
     * Whether the event passes every one of the trigger's "Only run when"
     * conditions (`config.filters`).
     *
     * Each condition compares a payload or header path using equals,
     * not_equals or contains. An unknown operator passes.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function matchesConfiguredFilters(Trigger $trigger, array $payload, array $headers): bool
    {
        $filters = $trigger->config['filters'] ?? [];

        foreach ($filters as $filter) {
            $subject = ($filter['source'] ?? 'payload') === 'header' ? $headers : $payload;
            $value = data_get($subject, $filter['path'] ?? '');

            // A filter that can't be evaluated (an operator this version
            // doesn't know) holds the event back rather than letting it through.
            try {
                $matches = ConditionEvaluator::matches($value, (string) ($filter['operator'] ?? 'equals'), $filter['value'] ?? null);
            } catch (InvalidArgumentException) {
                $matches = false;
            }

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Only the request headers allowed to be stored (`triggers.allowed_headers`).
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function allowedHeaders(array $headers): array
    {
        $allowList = array_map('strtolower', (array) config('triggers.allowed_headers', []));

        return array_filter(
            $headers,
            fn (string $key): bool => in_array(strtolower($key), $allowList, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Record an event that ends at intake: skipped or ignored.
     *
     * Unlike reject(), it keeps its delivery id, so a redelivery of the same
     * event is still recognised as a duplicate.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function writeTerminal(
        Trigger $trigger,
        TriggerType $source,
        array $payload,
        ?string $deliveryId,
        ?string $payloadSnippet,
        array $headers,
        TriggerEventStatus $status,
        ?string $error = null,
    ): TriggerEvent {
        try {
            return TriggerEvent::query()->create([
                'trigger_id' => $trigger->id,
                'source' => $source,
                'status' => $status,
                'payload' => $payload,
                'payload_snippet' => $payloadSnippet,
                'headers' => $this->allowedHeaders($headers),
                'delivery_id' => $deliveryId,
                'error' => $error,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateOf($trigger, (string) $deliveryId) ?? throw new UniqueConstraintViolationException;
        }
    }
}
