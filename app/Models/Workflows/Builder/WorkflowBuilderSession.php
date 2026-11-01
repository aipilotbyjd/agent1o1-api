<?php

namespace App\Models\Workflows\Builder;

use App\Enums\Workflows\BuilderMessageStatus;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\GraphValidator;
use App\Services\Workflows\NodeRegistry;
use Database\Factories\Workflows\Builder\WorkflowBuilderSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A chat session that edits a `draft_graph` on the user's behalf via
 * `WorkflowBuilderAgent`'s tools: every mutation method here is what those
 * tools actually call. `draft_graph` uses this project's own graph shape
 * (`{nodes: [...], edges: [...]}`, each node `{key, type, config, position}`);
 * exactly what `Workflow::replaceGraph()` expects, so promoting a draft to
 * a real workflow needs no adapter.
 */
#[Fillable([
    'workspace_id', 'user_id', 'workflow_id', 'workflow_graph_hash', 'title',
    'draft_graph', 'draft_lock_version', 'status', 'last_activity_at',
])]
class WorkflowBuilderSession extends Model
{
    public const string DEFAULT_TITLE = 'Untitled workflow';

    /**
     * Undo history kept per session. Every edit snapshots the whole graph
     * (an assistant turn can make a couple of dozen), so older snapshots
     * are pruned as new ones land: see `applyGraph()`.
     */
    public const int MAX_DRAFT_VERSIONS = 100;

    /**
     * A reply still "in flight" after this long lost its worker without the
     * job's `failed()` hook running (a hard kill, a deploy mid-turn): see
     * `failStaleReplies()`. Comfortably above the job's own timeout.
     */
    public const int STALE_REPLY_MINUTES = 10;

    /** @use HasFactory<WorkflowBuilderSessionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => self::DEFAULT_TITLE,
        'draft_lock_version' => 0,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draft_graph' => 'array',
            'draft_lock_version' => 'integer',
            'status' => BuilderSessionStatus::class,
            'last_activity_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function draftVersions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderDraftVersion::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WorkflowBuilderMessage::class, 'session_id');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{x: float, y: float}|null  $position
     */
    public function addNode(string $key, string $type, array $config = [], ?array $position = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        if (collect($graph['nodes'])->contains('key', $key)) {
            throw new InvalidArgumentException("Node [{$key}] already exists.");
        }

        $node = ['key' => $key, 'type' => $type, 'config' => $config, 'position' => $position];

        $this->assertNodeIsValid($node);

        $graph['nodes'][] = $node;

        $this->applyGraph($graph, $by, "Added node {$key}");
    }

    /**
     * Merge `$config` into a node's config and delete the top-level fields
     * named in `$removeFields`: the only way to take an optional field
     * (such as `_loop`) off a node, since merging can't express "unset".
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $removeFields
     */
    public function updateNode(string $key, array $config, ?User $by = null, array $removeFields = []): void
    {
        if ($config === [] && $removeFields === []) {
            throw new InvalidArgumentException('Nothing to update: pass config fields to set or fields to remove.');
        }

        if (($both = array_intersect($removeFields, array_keys($config))) !== []) {
            throw new InvalidArgumentException('A field can\'t be both set and removed: '.implode(', ', $both).'.');
        }

        $graph = $this->currentGraph();
        $found = false;

        foreach ($graph['nodes'] as &$node) {
            if ($node['key'] === $key) {
                $node['config'] = [...($node['config'] ?? []), ...$config];

                foreach ($removeFields as $field) {
                    unset($node['config'][$field]);
                }

                $this->assertNodeIsValid($node);
                $found = true;
                break;
            }
        }
        unset($node);

        if (! $found) {
            throw new InvalidArgumentException("Node [{$key}] was not found.");
        }

        $this->applyGraph($graph, $by, "Updated node {$key}");
    }

    /**
     * Reject a node whose config does not match its type's schema.
     *
     * Validating here (not in the agent's tools) means the agent gets the specific
     * missing or mistyped field back as a tool result and can correct itself, instead
     * of the mistake surfacing much later as a failed publish: see
     * `Workflow::replaceGraph()`'s identical draft-time-only validation.
     *
     * Flow-control types (loop, subflow, wait, human_approval, join_paths)
     * are placeable too; the two that run another workflow must point at a
     * published one in this workspace, which the engine would otherwise
     * only discover mid-run.
     *
     * `$requireKnownType` is off only where a canvas sync must be able to
     * save around a type the catalog can't check: see `replaceDraft()`.
     * `$requireFields` is off there too: a canvas the author is still
     * filling in saves like any draft, while a node the agent adds must come
     * complete, so it hears what's missing and fixes it.
     *
     * @param  array<string, mixed>  $node
     */
    private function assertNodeIsValid(array $node, bool $requireKnownType = true, bool $requireFields = true): void
    {
        $registry = app(NodeRegistry::class);
        $schema = $registry->configSchemaFor($node['type']);

        if ($schema === null && ! $requireKnownType) {
            return;
        }

        if ($schema === null) {
            throw new InvalidArgumentException(
                "There is no node for type [{$node['type']}]. Use list_available_nodes to see valid types.",
            );
        }

        $config = $node['config'] ?? [];
        $errors = app(ConfigSchemaValidator::class)->validate($schema, $config, requireFields: $requireFields);

        if ($errors !== []) {
            throw new InvalidArgumentException("Node '{$node['key']}': ".implode(' ', $errors));
        }

        $flowControl = FlowControlNodeType::tryFrom($node['type']);

        if ($flowControl !== null && array_key_exists('_loop', $config)) {
            throw new InvalidArgumentException("Node '{$node['key']}': loop mode (_loop) isn't supported on flow-control nodes.");
        }

        if ($flowControl?->runsChildWorkflow()) {
            $this->assertRunnableChildWorkflow($node['key'], (string) $config['workflow_id']);
        }
    }

    private function assertRunnableChildWorkflow(string $key, string $workflowId): void
    {
        if ($this->workflow_id !== null && $workflowId === $this->workflow_id) {
            throw new InvalidArgumentException("Node '{$key}': a workflow can't run itself as a child workflow.");
        }

        $child = Str::isUuid($workflowId)
            ? Workflow::query()->whereKey($workflowId)->where('workspace_id', $this->workspace_id)->first()
            : null;

        if ($child === null) {
            throw new InvalidArgumentException("Node '{$key}': there is no workflow [{$workflowId}] in this workspace. Use list_workflows to find one.");
        }

        if (! $child->isPublished()) {
            throw new InvalidArgumentException("Node '{$key}': workflow [{$child->name}] isn't published yet, so it can't run as a child workflow.");
        }
    }

    public function removeNode(string $key, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        if (! collect($graph['nodes'])->contains('key', $key)) {
            throw new InvalidArgumentException("Node [{$key}] was not found.");
        }

        $graph['nodes'] = array_values(array_filter($graph['nodes'], fn (array $node): bool => $node['key'] !== $key));
        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => $edge['from'] !== $key && $edge['to'] !== $key,
        ));

        $this->applyGraph($graph, $by, "Removed node {$key}");
    }

    public function connect(string $from, string $to, ?string $condition = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $keys = collect($graph['nodes'])->pluck('key');

        if (! $keys->contains($from) || ! $keys->contains($to)) {
            throw new InvalidArgumentException('Both nodes must exist in the draft before they can be connected.');
        }

        $exists = collect($graph['edges'])->contains(
            fn (array $edge): bool => $edge['from'] === $from && $edge['to'] === $to && ($edge['condition'] ?? null) === $condition,
        );

        if ($exists) {
            throw new InvalidArgumentException("[{$from}] is already connected to [{$to}] with that condition.");
        }

        $graph['edges'][] = ['from' => $from, 'to' => $to, 'condition' => $condition];

        $this->applyGraph($graph, $by, "Connected {$from} → {$to}");
    }

    /**
     * Remove the edges from `$from` to `$to`: every one of them, or with
     * `$onlyCondition` just the one carrying `$condition` (`null` meaning the
     * unconditional edge), e.g. to turn an always-edge into an error path.
     */
    public function disconnect(string $from, string $to, ?User $by = null, ?string $condition = null, bool $onlyCondition = false): void
    {
        $graph = $this->currentGraph();
        $edgeCount = count($graph['edges']);

        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => ! ($edge['from'] === $from
                && $edge['to'] === $to
                && (! $onlyCondition || ($edge['condition'] ?? null) === $condition)),
        ));

        if (count($graph['edges']) === $edgeCount) {
            throw new InvalidArgumentException($onlyCondition
                ? "There is no edge from [{$from}] to [{$to}] with that condition."
                : "There is no edge from [{$from}] to [{$to}].");
        }

        $this->applyGraph($graph, $by, "Disconnected {$from} → {$to}");
    }

    /**
     * Replace the whole draft with the canvas's copy of it: how edits made
     * by hand (drag, delete, add from the library) reach the draft the agent
     * reads. Refused when `$expectedLockVersion` is behind, so a canvas that
     * hasn't seen the agent's latest edits can't silently undo them.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     *
     * @throws InvalidArgumentException
     * @throws WorkflowBuilderConflictException
     */
    public function replaceDraft(array $graph, int $expectedLockVersion, ?User $by = null): void
    {
        if ($expectedLockVersion !== $this->draft_lock_version) {
            throw WorkflowBuilderConflictException::staleDraft();
        }

        $normalized = [
            'nodes' => array_map(fn (array $node): array => [
                'key' => $node['key'],
                'type' => $node['type'],
                'config' => $node['config'] ?? [],
                'position' => $node['position'] ?? null,
            ], $graph['nodes']),
            'edges' => GraphValidator::uniqueEdges(array_map(fn (array $edge): array => [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'condition' => $edge['condition'] ?? null,
            ], $graph['edges'])),
        ];

        $structuralErrors = app(GraphValidator::class)->structuralErrors($normalized['nodes'], $normalized['edges']);

        if ($structuralErrors !== []) {
            throw new InvalidArgumentException(implode(' ', $structuralErrors));
        }

        // The canvas may save a type the catalog has no schema for when it
        // is a `custom:` node (which a workflow may hold by design), or
        // when the node is carried over unchanged from the draft, e.g. from
        // the workflow the session was opened on, which `Workflow::replaceGraph()`
        // accepted. A node the canvas adds or retypes to an unknown type is
        // still refused. Publishing refuses unknown types either way
        // (`GraphValidator::validate()`).
        $registry = app(NodeRegistry::class);
        $currentTypes = collect($this->currentGraph()['nodes'])->pluck('type', 'key');

        foreach ($normalized['nodes'] as $node) {
            $isCarriedOver = $currentTypes->get($node['key']) === $node['type'];

            $this->assertNodeIsValid($node, requireKnownType: ! $isCarriedOver && ! $registry->isCustom($node['type']), requireFields: false);
        }

        $this->applyGraph($normalized, $by, 'Synced from canvas');
    }

    /**
     * Roll the draft back to an earlier snapshot. The restore is itself a new
     * snapshot, so it can be undone the same way. With
     * `$expectedLockVersion`, refused when the draft moved on since the
     * caller last loaded it, so an undo can't silently throw away
     * assistant edits the user hasn't seen yet.
     *
     * @throws WorkflowBuilderConflictException
     */
    public function restoreVersion(WorkflowBuilderDraftVersion $version, ?User $by = null, ?int $expectedLockVersion = null): void
    {
        if ($version->session_id !== $this->id) {
            throw new InvalidArgumentException('That version belongs to a different session.');
        }

        if ($expectedLockVersion !== null && $expectedLockVersion !== $this->draft_lock_version) {
            throw WorkflowBuilderConflictException::staleDraft();
        }

        $this->applyGraph(
            $version->graph_snapshot,
            $by,
            'Restored '.($version->label ?? 'version from '.$version->created_at?->format('M j, H:i')),
        );
    }

    /**
     * @throws WorkflowBuilderConflictException
     */
    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw WorkflowBuilderConflictException::archived();
        }
    }

    /**
     * Fail assistant replies that have been in flight longer than any turn
     * can run, so a lost worker doesn't block the session forever.
     */
    public function failStaleReplies(): void
    {
        $this->messages()
            ->where('role', 'assistant')
            ->whereIn('processing_status', [BuilderMessageStatus::Pending, BuilderMessageStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes(self::STALE_REPLY_MINUTES))
            ->update([
                'processing_status' => BuilderMessageStatus::Failed,
                'error_message' => ProcessWorkflowBuilderMessageJob::FAILURE_MESSAGE,
                'updated_at' => now(),
            ]);
    }

    public function hasReplyInFlight(): bool
    {
        return $this->messages()
            ->where('role', 'assistant')
            ->whereIn('processing_status', [BuilderMessageStatus::Pending, BuilderMessageStatus::Processing])
            ->exists();
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function currentGraph(): array
    {
        return $this->draft_graph ?? ['nodes' => [], 'edges' => []];
    }

    /**
     * Persist a mutated graph as both the live draft and a labelled snapshot, so every
     * edit is individually diffable/undoable via draftVersions().
     *
     * The write only lands if `draft_lock_version` still matches the one this
     * instance read: the agent (inside a queued turn) and the canvas (over
     * HTTP) edit the same draft, and a stale writer would otherwise silently
     * erase the other's change. It also refuses once the session is
     * archived, so a turn still running when that happens stops editing.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     *
     * @throws WorkflowBuilderConflictException
     */
    private function applyGraph(array $graph, ?User $by, string $label): void
    {
        $nextVersion = $this->draft_lock_version + 1;

        DB::transaction(function () use ($graph, $by, $label, $nextVersion): void {
            $updated = static::query()
                ->whereKey($this->getKey())
                ->where('draft_lock_version', $this->draft_lock_version)
                ->where('status', '!=', BuilderSessionStatus::Archived)
                ->update([
                    'draft_graph' => json_encode($graph, JSON_THROW_ON_ERROR),
                    'draft_lock_version' => $nextVersion,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw static::query()->whereKey($this->getKey())->toBase()->value('status') === BuilderSessionStatus::Archived->value
                    ? WorkflowBuilderConflictException::archived()
                    : WorkflowBuilderConflictException::staleDraft();
            }

            $this->draftVersions()->create([
                'triggered_by' => $by?->id,
                'graph_snapshot' => $graph,
                'label' => $label,
            ]);

            $this->pruneDraftVersions();
        });

        $this->forceFill([
            'draft_graph' => $graph,
            'draft_lock_version' => $nextVersion,
            'last_activity_at' => now(),
        ])->syncOriginal();
    }

    /**
     * Drop the snapshots older than the newest `MAX_DRAFT_VERSIONS`. Each
     * edit adds one, so in steady state this deletes one row per edit.
     */
    private function pruneDraftVersions(): void
    {
        $staleIds = $this->draftVersions()
            ->latest()
            ->latest('id')
            ->skip(self::MAX_DRAFT_VERSIONS)
            ->take(500)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            WorkflowBuilderDraftVersion::query()->whereKey($staleIds->all())->delete();
        }
    }
}
