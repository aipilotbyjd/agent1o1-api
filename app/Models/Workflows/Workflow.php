<?php

namespace App\Models\Workflows;

use App\Exceptions\PublishApprovalRequiredException;
use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerNodeSync;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\GraphValidator;
use App\Services\Workflows\LoopModeCompiler;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workflows\ValidationIssue;
use Database\Factories\Workflows\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

#[Fillable(['workspace_id', 'folder_id', 'name', 'slug', 'description', 'input_schema', 'created_by', 'is_internal'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'has_unpublished_changes' => false,
        'is_internal' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_unpublished_changes' => 'boolean',
            'input_schema' => 'array',
            'is_internal' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_workflow');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_favorites')->withTimestamps();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(WorkflowPage::class);
    }

    public function publishRequests(): HasMany
    {
        return $this->hasMany(WorkflowPublishRequest::class);
    }

    public function isPublished(): bool
    {
        return $this->current_version_id !== null;
    }

    /**
     * Excludes the hidden, single-node child workflows
     * `Services\Workflows\LoopModeCompiler` auto-manages for a looped node:
     * a user never authored these and shouldn't see them listed, and they
     * shouldn't count toward `PlanLimit::Workflows`.
     *
     * @param  Builder<Workflow>  $query
     * @return Builder<Workflow>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    /**
     * Replace the draft graph wholesale. Only per-node config-schema issues
     * are checked here (not the full `GraphValidator` sequence: a draft is
     * allowed to be mid-edit, e.g. temporarily missing an entry node, or a
     * newly added node whose required fields aren't filled in yet; publishing
     * still requires them): see docs/WORKFLOWS_PLAN.md's "Validation" section.
     *
     * @param  array{nodes?: array<int, array{key: string, type: string, config?: array<string, mixed>, position?: array<string, mixed>|null}>, edges?: array<int, array{from: string, to: string, condition?: string|null}>}  $graph
     *
     * @throws WorkflowValidationException
     */
    public function replaceGraph(array $graph): void
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = GraphValidator::uniqueEdges($graph['edges'] ?? []);

        $registry = app(NodeRegistry::class);
        $configValidator = app(ConfigSchemaValidator::class);

        $errors = app(GraphValidator::class)->structuralIssues($nodes, $edges);

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        foreach ($nodes as $node) {
            $schema = $registry->configSchemaFor($node['type']);

            if ($schema === null) {
                continue;
            }

            foreach ($configValidator->issues($schema, $node['config'] ?? [], requireFields: false) as $issue) {
                $errors[] = ValidationIssue::forNodeConfig($node['key'], $issue);
            }
        }

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        DB::transaction(function () use ($nodes, $edges): void {
            // Pin state lives on the node row, but every save deletes and
            // recreates the rows wholesale (fresh IDs): snapshot pinned
            // data keyed by `key` first so it survives an ordinary canvas
            // save. A node whose `type` changed loses its pin: the output
            // shape it was pinned against may no longer apply.
            $previousNodesByKey = $this->nodes()->get()->keyBy('key');

            $this->edges()->delete();
            $this->nodes()->delete();

            $nodeIdsByKey = [];

            foreach ($nodes as $node) {
                $previous = $previousNodesByKey->get($node['key']);
                $carryPin = $previous !== null && $previous->type === $node['type'];

                $nodeIdsByKey[$node['key']] = $this->nodes()->create([
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'config' => $node['config'] ?? [],
                    'position' => $node['position'] ?? null,
                    'pinned_data' => $carryPin ? $previous->pinned_data : null,
                    'pinned_at' => $carryPin ? $previous->pinned_at : null,
                    'pinned_by' => $carryPin ? $previous->pinned_by : null,
                ])->id;
            }

            foreach ($edges as $edge) {
                $this->edges()->create([
                    'from_node_id' => $nodeIdsByKey[$edge['from']],
                    'to_node_id' => $nodeIdsByKey[$edge['to']],
                    'condition' => $edge['condition'] ?? null,
                ]);
            }

            $this->forceFill(['has_unpublished_changes' => true])->save();
        });
    }

    /**
     * The live draft as the same `{nodes, edges}` shape a `WorkflowVersion`
     * snapshot uses: what `GraphValidator`, `DryRunner` and
     * `publishVersion()` all consume, so the thing being validated is always
     * the thing that would be published.
     *
     * @return array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>, pinned_data: array<string, mixed>|null}>, edges: array<int, array{from: string, to: string, condition: string|null}>}
     */
    public function draftGraph(): array
    {
        $nodes = $this->nodes()->get();

        $nodeKeysById = $nodes->pluck('key', 'id');

        return [
            'nodes' => $nodes
                ->map(fn (WorkflowNode $node): array => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'config' => $node->config ?? [],
                    'pinned_data' => $node->pinned_data,
                ])
                ->all(),
            'edges' => $this->edges()->get()
                ->map(fn (WorkflowEdge $edge): array => [
                    'from' => $nodeKeysById[$edge->from_node_id],
                    'to' => $nodeKeysById[$edge->to_node_id],
                    'condition' => $edge->condition,
                ])
                ->all(),
        ];
    }

    /**
     * The draft as the editor sees it: `draftGraph()` plus each node's
     * canvas position, minus pins. This is what a version records as its
     * `editor_graph` and what `restoreVersion()` writes back.
     *
     * @return array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>, position: array<string, mixed>|null}>, edges: array<int, array{from: string, to: string, condition: string|null}>}
     */
    public function editorGraph(): array
    {
        $nodes = $this->nodes()->get();
        $nodeKeysById = $nodes->pluck('key', 'id');

        return [
            'nodes' => $nodes
                ->map(fn (WorkflowNode $node): array => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'config' => $node->config ?? [],
                    'position' => $node->position,
                ])
                ->all(),
            'edges' => $this->edges()->get()
                ->map(fn (WorkflowEdge $edge): array => [
                    'from' => $nodeKeysById[$edge->from_node_id],
                    'to' => $nodeKeysById[$edge->to_node_id],
                    'condition' => $edge->condition,
                ])
                ->all(),
        ];
    }

    /**
     * Replace the draft with what a published version looked like. Nothing
     * goes live: the author reviews it and publishes like any other edit.
     * Restoring the live version is "discard changes", so the draft is then
     * no longer ahead of it.
     *
     * @throws WorkflowValidationException
     */
    public function restoreVersion(WorkflowVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $this->replaceGraph($version->editorGraph());

            if ($version->id === $this->current_version_id) {
                $this->forceFill(['has_unpublished_changes' => false])->save();
            }
        });
    }

    /**
     * A hash of the draft graph as an author sees it: keys, types, configs,
     * positions and edges, independent of row order. Pins are left out:
     * pinning sample data isn't an edit to the workflow. A builder session
     * records it (`workflow_graph_hash`) when it loads or promotes into this
     * workflow, so promoting can tell whether someone edited the workflow
     * in the meantime.
     */
    public function graphFingerprint(): string
    {
        $nodes = $this->nodes()->get();
        $nodeKeysById = $nodes->pluck('key', 'id');

        $graph = [
            'nodes' => $nodes
                ->map(fn (WorkflowNode $node): array => [
                    'key' => $node->key,
                    'type' => $node->type,
                    'config' => $node->config ?? [],
                    'position' => $node->position,
                ])
                ->sortBy('key')
                ->values()
                ->all(),
            'edges' => $this->edges()->get()
                ->map(fn (WorkflowEdge $edge): array => [
                    'from' => $nodeKeysById[$edge->from_node_id],
                    'to' => $nodeKeysById[$edge->to_node_id],
                    'condition' => $edge->condition,
                ])
                ->sortBy(fn (array $edge): string => json_encode($edge, JSON_THROW_ON_ERROR))
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($graph, JSON_THROW_ON_ERROR));
    }

    /**
     * Snapshot the current draft graph as an immutable, publish-pinned
     * `WorkflowVersion`: see `publishGraph()`. Refused while the workspace
     * requires changes to be reviewed first: those go live through
     * `PublishRequestService::approve()` instead. Hidden child workflows
     * (`LoopModeCompiler`) are published as part of their parent and are
     * never reviewed on their own.
     *
     * @throws WorkflowValidationException
     * @throws PublishApprovalRequiredException
     */
    public function publishVersion(?string $notes = null, ?User $publisher = null): WorkflowVersion
    {
        if (! $this->is_internal && $this->workspace->requiresPublishApproval()) {
            throw new PublishApprovalRequiredException;
        }

        return $this->publishGraph($this->draftGraph(), $this->editorGraph(), $notes, $publisher);
    }

    /**
     * Snapshot a graph as the next version after running the full
     * `GraphValidator` sequence: see docs/WORKFLOWS_PLAN.md's
     * `workflow_versions` section for why runs pin to a version rather than
     * the live draft. The graph is normally the draft; an approved publish
     * request passes the copy it was reviewed with.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $draft
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $editorGraph
     *
     * @throws WorkflowValidationException
     */
    public function publishGraph(array $draft, array $editorGraph, ?string $notes = null, ?User $publisher = null): WorkflowVersion
    {
        ['nodes' => $nodes, 'edges' => $edges] = $draft;

        $errors = app(GraphValidator::class)->issues($nodes, $edges, $this->workspace_id);

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        // Compiles any node carrying a `config._loop` key into a real `loop`
        // node pointed at an auto-managed child workflow: see
        // `LoopModeCompiler`'s docblock. Graph-shape validation above already
        // ran against the original node (same key, same edges), so nothing
        // about the graph's structure needs revalidating after this.
        $nodes = app(LoopModeCompiler::class)->compile($this, $nodes);

        // What was live until now: `TriggerNodeSync` compares each trigger
        // node's "Activate" switch against it, to tell an author's change
        // from a trigger the circuit breaker switched off since.
        $previousNodes = $this->currentVersion?->graph['nodes'] ?? [];

        return DB::transaction(function () use ($nodes, $edges, $editorGraph, $notes, $publisher, $previousNodes): WorkflowVersion {
            $graph = ['nodes' => $nodes, 'edges' => $edges];

            // `lockForUpdate()` on the watermark read, plus a retry on the
            // `unique(workflow_id, version)` constraint: the same pairing
            // `AgentVersioner::snapshot()` uses. Two people hitting Publish
            // at once would otherwise both read the same `max('version')`
            // and the loser's request would die on the constraint. The lock
            // alone isn't enough: on a workflow's *first* publish there are
            // no rows to lock, so both readers see null and both try 1.
            //
            // The first attempt runs in a nested transaction, i.e. a
            // savepoint, because Postgres aborts the whole transaction on a
            // constraint violation, which would leave the retry below unable
            // to issue any query at all. Rolling back to the savepoint keeps
            // the outer transaction usable.
            try {
                $version = DB::transaction(fn (): WorkflowVersion => $this->createVersion(
                    ((int) $this->versions()->lockForUpdate()->pluck('version')->max()) + 1,
                    $graph,
                    $editorGraph,
                    $notes,
                    $publisher,
                ));
            } catch (UniqueConstraintViolationException) {
                $version = $this->createVersion(
                    ((int) $this->versions()->max('version')) + 1,
                    $graph,
                    $editorGraph,
                    $notes,
                    $publisher,
                );
            }

            $this->forceFill([
                'current_version_id' => $version->id,
                'has_unpublished_changes' => false,
                'status' => 'published',
            ])->save();

            // The live triggers follow the version that just went live: see
            // `TriggerNodeSync`. Same transaction, so a trigger node that
            // can't be materialized (an account that's gone) fails the
            // publish instead of leaving a half-synced workflow.
            app(TriggerNodeSync::class)->sync($this, $nodes, $publisher, $previousNodes);

            return $version;
        });
    }

    /**
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $editorGraph
     */
    private function createVersion(int $version, array $graph, array $editorGraph, ?string $notes, ?User $publisher): WorkflowVersion
    {
        return $this->versions()->create([
            'version' => $version,
            'graph' => $graph,
            'editor_graph' => $editorGraph,
            'notes' => $notes,
            'published_by' => $publisher?->id,
        ]);
    }
}
