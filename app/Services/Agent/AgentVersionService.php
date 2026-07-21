<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Versioning & rollback (roadmap item 10).
 *
 * Snapshots an agent's behaviour-defining config on every save, so edits to
 * instructions / model / tools / skills / advanced settings can be diffed and
 * rolled back. Snapshots are immutable; a rollback creates a *new* version from
 * the target snapshot rather than deleting history.
 */
class AgentVersionService
{
    /**
     * The agent attributes that define its behaviour and are worth versioning.
     *
     * @var list<string>
     */
    private const TRACKED = [
        'name', 'description', 'instructions', 'model', 'provider',
        'max_steps', 'timeout_seconds', 'category', 'default_workflow_id',
        'planning_enabled', 'reflection_enabled', 'reflection_interval',
        'child_agent_ids', 'memory_auto_extract', 'memory_semantic_recall',
        'memory_recall_limit', 'code_execution_enabled', 'web_browsing_enabled',
        'tool_cache_enabled', 'guardrails', 'max_tokens_per_run',
        'daily_token_budget', 'daily_cost_budget',
    ];

    /**
     * Build a snapshot of the agent's current behaviour-defining config.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Agent $agent): array
    {
        $agent->loadMissing(['toolConfigs', 'skills']);

        $data = [];
        foreach (self::TRACKED as $key) {
            $data[$key] = $agent->getAttribute($key);
        }

        $data['tools'] = $agent->toolConfigs
            ->map(fn ($c) => [
                'node_type' => $c->node_type,
                'tool_name' => $c->tool_name,
                'tool_description' => $c->tool_description,
                'is_enabled' => $c->is_enabled,
                'sort_order' => $c->sort_order,
            ])
            ->values()
            ->all();

        $data['skill_ids'] = $agent->skills->pluck('id')->all();

        return $data;
    }

    /**
     * Persist a new version if the agent's config changed since the last one.
     * Returns the created version, or null when nothing changed.
     */
    public function record(Agent $agent, ?User $author = null, ?string $label = null): ?AgentVersion
    {
        $snapshot = $this->snapshot($agent);
        $latest = $agent->versions()->first();

        if ($latest && $this->normalize($latest->snapshot) === $this->normalize($snapshot)) {
            return null;
        }

        $nextVersion = (int) ($agent->versions()->max('version') ?? 0) + 1;

        return $agent->versions()->create([
            'workspace_id' => $agent->workspace_id,
            'created_by' => $author?->id,
            'version' => $nextVersion,
            'label' => $label,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Restore an agent to a stored version, recording the restore as a new
     * version so history stays linear and auditable.
     */
    public function rollback(Agent $agent, AgentVersion $version, ?User $author = null): Agent
    {
        return DB::transaction(function () use ($agent, $version, $author) {
            $snapshot = $version->snapshot;

            $attributes = [];
            foreach (self::TRACKED as $key) {
                if (array_key_exists($key, $snapshot)) {
                    $attributes[$key] = $snapshot[$key];
                }
            }
            $agent->update($attributes);

            // Rebuild tool configs from the snapshot.
            $agent->toolConfigs()->delete();
            foreach (($snapshot['tools'] ?? []) as $index => $tool) {
                $agent->toolConfigs()->create([
                    'node_type' => $tool['node_type'],
                    'tool_name' => $tool['tool_name'] ?? null,
                    'tool_description' => $tool['tool_description'] ?? '',
                    'is_enabled' => $tool['is_enabled'] ?? true,
                    'sort_order' => $tool['sort_order'] ?? $index,
                ]);
            }

            // Re-sync skills that still exist in the workspace.
            if (isset($snapshot['skill_ids'])) {
                $agent->skills()->sync($snapshot['skill_ids']);
            }

            $fresh = $agent->fresh(['toolConfigs', 'skills']);

            $this->record($fresh, $author, "Rolled back to v{$version->version}");

            return $fresh;
        });
    }

    /**
     * Field-level diff between two snapshots (or a snapshot and the live agent).
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(array $from, array $to): array
    {
        $keys = array_unique([...array_keys($from), ...array_keys($to)]);
        $changes = [];

        foreach ($keys as $key) {
            $before = $from[$key] ?? null;
            $after = $to[$key] ?? null;

            if ($this->normalizeValue($before) !== $this->normalizeValue($after)) {
                $changes[$key] = ['from' => $before, 'to' => $after];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function normalize(array $snapshot): string
    {
        ksort($snapshot);

        return json_encode(array_map(fn ($v) => $this->normalizeValue($v), $snapshot)) ?: '';
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
