<?php

namespace App\Services\Triggers;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Billing\PlanLimit;
use App\Enums\Triggers\TriggerNodeType;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Enums\Triggers\WebhookAuthentication;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\WorkflowValidationException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Billing\PlanLimitGate;
use App\Services\Timezones\Timezones;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workflows\ValidationIssue;
use Closure;

/**
 * Keeps a workflow's `triggers` rows in step with its published graph.
 *
 * Each schedule, webhook and polling node gets one row, and so does each
 * reader node switched on as a flow trigger (as a polling trigger over the
 * node's own execute()). Rows whose node was removed are deleted.
 *
 * It runs inside Workflow::publishGraph()'s transaction, which every publish
 * goes through (direct, approved request, restore and publish), so the live
 * triggers always match the published version, never a draft.
 *
 * A republish overwrites only what the node owns. The URL token and signing
 * secret are kept, so a webhook URL stays the same. On/off follows the
 * "Activate as flow trigger" switch when the row is created, and afterwards
 * only when the author flipped the switch since the last publish, so a
 * trigger the circuit breaker switched off isn't quietly switched back on.
 *
 * @see docs/TRIGGERS_PLAN.md §2
 */
class TriggerNodeSync
{
    public function __construct(
        private readonly PlanLimitGate $limits,
        private readonly TriggerNodes $triggerNodes,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * A reader node's trigger-mode settings, kept out of the config its own
     * execute() is polled with.
     */
    private const array APP_TRIGGER_KEYS = ['activate', 'poll_interval_minutes', 'if_already_running', 'max_failures', 'max_concurrent_runs', 'max_runs_per_minute', 'batch_seconds', 'debounce_seconds', 'storm_limit', 'start_from'];

    /**
     * The polling node settings PollTrigger reads.
     */
    private const array POLLING_KEYS = [
        'url', 'method', 'items_path', 'id_path', 'newest_first', 'start_from', 'poll_interval_minutes', 'cursor_param', 'cursor_path', 'filters',
    ];

    /**
     * Create, update and delete the workflow's node-owned trigger rows to
     * match the graph being published.
     *
     * @param  array<int, array{key: string, type: string, config?: array<string, mixed>}>  $nodes  the graph being published
     * @param  array<int, array{key: string, type: string, config?: array<string, mixed>}>  $previousNodes  the graph that was live before this publish
     *
     * @throws WorkflowValidationException
     * @throws PlanLimitExceededException
     */
    public function sync(Workflow $workflow, array $nodes, ?User $actor = null, array $previousNodes = []): void
    {
        $previousConfigs = collect($previousNodes)->mapWithKeys(fn (array $node): array => [$node['key'] => $node['config'] ?? []]);

        $existing = Trigger::query()
            ->where('target_type', TriggerTargetType::Workflow->value)
            ->where('target_id', $workflow->id)
            ->whereNotNull('trigger_node_key')
            ->get()
            ->keyBy('trigger_node_key');

        $keptKeys = [];

        foreach ($nodes as $node) {
            $row = $this->rowFor($workflow, $node, $existing->get($node['key']), $actor);

            if ($row === null) {
                continue;
            }

            $keptKeys[] = $node['key'];
            $previousConfig = $previousConfigs->get($node['key']);

            $this->upsert(
                $workflow,
                $node['key'],
                $row,
                $previousConfig === null ? null : $row['is_on']($previousConfig),
                $existing->get($node['key']),
                $actor,
            );
        }

        $existing
            ->reject(fn (Trigger $trigger): bool => in_array($trigger->trigger_node_key, $keptKeys, true))
            ->each(fn (Trigger $trigger) => $trigger->delete());
    }

    /**
     * The trigger row a node should have, or null if it shouldn't have one:
     * it isn't a trigger, it's a manual trigger, or it's a reader node whose
     * "Activate as flow trigger" switch is off.
     *
     * @param  array{key: string, type: string, config?: array<string, mixed>}  $node
     * @return array{type: TriggerType, config: array<string, mixed>, preset_id: ?string, credential_id: ?string, is_on: Closure(array<string, mixed>): bool, active: bool}|null
     *
     * @throws WorkflowValidationException
     */
    private function rowFor(Workflow $workflow, array $node, ?Trigger $existing, ?User $actor): ?array
    {
        $config = $node['config'] ?? [];
        $key = $node['key'];
        $nodeType = TriggerNodeType::tryFrom((string) $node['type']);

        if ($nodeType !== null) {
            if (! $nodeType->materializes()) {
                return null;
            }

            return [
                'type' => $nodeType->triggerType(),
                'config' => $this->configFor($nodeType, $config),
                'preset_id' => null,
                'credential_id' => $nodeType === TriggerNodeType::Polling ? $this->credentialId($workflow, $key, $config, $existing, $actor) : null,
                'is_on' => self::isActivated(...),
                'active' => self::isActivated($config),
            ];
        }

        if (! $this->triggerNodes->supportsTriggerMode((string) $node['type']) || ! TriggerNodes::isAppTriggerOn($config)) {
            return null;
        }

        /** @var SupportsTriggerMode $reader */
        $reader = $this->registry->resolve((string) $node['type']);

        return [
            'type' => TriggerType::Polling,
            'config' => array_filter([
                'node_type' => $node['type'],
                // What the reader's own execute() is called with on each poll.
                'node_config' => array_diff_key($config, array_flip(self::APP_TRIGGER_KEYS)),
                'poll_interval_minutes' => (int) ($config['poll_interval_minutes'] ?? $reader->triggerPollMinutes()),
                'if_already_running' => $config['if_already_running'] ?? null,
                'max_failures' => $config['max_failures'] ?? null,
                'max_concurrent_runs' => $config['max_concurrent_runs'] ?? null,
                'max_runs_per_minute' => $config['max_runs_per_minute'] ?? null,
                'batch_seconds' => $config['batch_seconds'] ?? null,
                'debounce_seconds' => $config['debounce_seconds'] ?? null,
                'storm_limit' => $config['storm_limit'] ?? null,
                'start_from' => $config['start_from'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'preset_id' => null,
            'credential_id' => $this->credentialId($workflow, $key, $config, $existing, $actor),
            'is_on' => TriggerNodes::isAppTriggerOn(...),
            'active' => true,
        ];
    }

    /**
     * Create or update one node's trigger row.
     *
     * @param  array{type: TriggerType, config: array<string, mixed>, preset_id: ?string, credential_id: ?string, active: bool}  $row
     * @param  bool|null  $wasOn  the switch in the last published version; null if the node wasn't in it
     *
     * @throws PlanLimitExceededException
     */
    private function upsert(Workflow $workflow, string $key, array $row, ?bool $wasOn, ?Trigger $trigger, ?User $actor): void
    {
        if ($trigger === null) {
            $trigger = (new Trigger)->forceFill([
                'workspace_id' => $workflow->workspace_id,
                'target_type' => TriggerTargetType::Workflow->value,
                'target_id' => $workflow->id,
                'trigger_node_key' => $key,
                'is_active' => $row['active'],
                'created_by' => $actor?->id,
            ]);
        } elseif ($wasOn !== $row['active']) {
            $trigger->forceFill(['is_active' => $row['active']]);

            if ($row['active']) {
                $trigger->forceFill(['consecutive_failure_count' => 0, 'consecutive_skip_count' => 0, 'disabled_reason' => null]);
            }
        }

        $trigger->forceFill([
            'type' => $row['type'],
            'config' => $row['config'],
            'preset_id' => $row['preset_id'],
            'credential_id' => $row['credential_id'],
        ]);

        // A node whose type changed (webhook to schedule, say) keeps its row
        // but not a URL it no longer answers on.
        if (! $row['type']->usesToken()) {
            $trigger->token = null;
        } elseif ($trigger->token === null) {
            $trigger->token = Trigger::generateToken();
        }

        // Switching on, whether new or off until now, has to fit the plan's
        // cap on active triggers; rows saved earlier in this publish count.
        // A new row is checked via `exists`, because Trigger defaults
        // is_active to true and so a new row's original value is already on.
        if ($trigger->is_active && (! $trigger->exists || ! $trigger->getOriginal('is_active'))) {
            $this->limits->assertCanCreate($workflow->workspace, PlanLimit::ActiveTriggers);
        }

        $trigger->save();
    }

    /**
     * Whether a dedicated trigger node's "Activate as flow trigger" switch is
     * on. It's on unless explicitly switched off.
     *
     * @param  array<string, mixed>  $config
     */
    public static function isActivated(array $config): bool
    {
        return ($config['activate'] ?? true) !== false;
    }

    /**
     * The settings a dedicated trigger node passes to its row: only what that
     * type uses, with empty values dropped.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function configFor(TriggerNodeType $nodeType, array $config): array
    {
        $runControl = array_intersect_key($config, array_flip(['if_already_running', 'max_failures', 'max_concurrent_runs', 'max_runs_per_minute', 'batch_seconds', 'debounce_seconds', 'storm_limit']));

        $owned = $runControl + match ($nodeType) {
            TriggerNodeType::Schedule => ['cron' => $config['cron'] ?? null, 'run_once_at' => $config['run_once_at'] ?? null, 'timezone' => Timezones::canonical($config['timezone'] ?? null)],
            TriggerNodeType::Webhook => [
                'authentication' => $config['authentication'] ?? WebhookAuthentication::None->value,
                'header_name' => $config['header_name'] ?? null,
                'filters' => $config['filters'] ?? null,
                'response_mode' => $config['response_mode'] ?? null,
                'response_timeout_seconds' => $config['response_timeout_seconds'] ?? null,
                'pass_headers' => $config['pass_headers'] ?? null,
            ],
            TriggerNodeType::Polling => array_intersect_key($config, array_flip(self::POLLING_KEYS)),
            TriggerNodeType::Manual => [],
        };

        return array_filter($owned, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * The connected account a polling node uses, checked at publish.
     *
     * It must be one the trigger's owner could pick themselves: a team
     * account, or their own personal one. Otherwise anyone could point a poll
     * at someone else's private connection.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws WorkflowValidationException
     */
    private function credentialId(Workflow $workflow, string $key, array $config, ?Trigger $trigger, ?User $actor): ?string
    {
        $credentialId = $config['credential_id'] ?? null;

        if (blank($credentialId)) {
            return null;
        }

        $owner = $trigger?->creator ?? $actor;

        $credential = ConnectorCredential::query()
            ->where('workspace_id', $workflow->workspace_id)
            ->whereKey($credentialId)
            ->first();

        if ($credential === null || ($owner !== null && ! $credential->isVisibleTo($owner))) {
            throw new WorkflowValidationException([new ValidationIssue('That account is not available. Pick another one.', $key, 'credential_id')]);
        }

        return $credential->id;
    }
}
