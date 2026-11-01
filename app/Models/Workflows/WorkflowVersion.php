<?php

namespace App\Models\Workflows;

use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Runs\Run;
use App\Models\User;
use Database\Factories\Workflows\WorkflowVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Fillable(['workflow_id', 'version', 'graph', 'editor_graph', 'notes', 'published_by'])]
class WorkflowVersion extends Model
{
    /** @use HasFactory<WorkflowVersionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graph' => 'array',
            'editor_graph' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    /**
     * The graph to show on the canvas and restore into the draft. Versions
     * published before `editor_graph` existed fall back to the runnable
     * `graph`: same keys and edges, but no positions (the editor lays them
     * out), with loop-mode steps turned back into the step they were
     * compiled from: see `authoredLoopNode()`.
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function editorGraph(): array
    {
        if ($this->editor_graph !== null) {
            return $this->editor_graph;
        }

        return [
            'nodes' => array_map(
                fn (array $node): array => [
                    ...($this->authoredLoopNode($node) ?? [
                        'key' => $node['key'],
                        'type' => $node['type'],
                        'config' => $node['config'] ?? [],
                    ]),
                    'position' => null,
                ],
                $this->graph['nodes'] ?? [],
            ),
            'edges' => $this->graph['edges'] ?? [],
        ];
    }

    /**
     * Undo `LoopModeCompiler` for one node: a `loop` node pointing at the
     * hidden child workflow the compiler made for this key becomes the
     * child's step again, with its `_loop` settings. The child is republished
     * on every parent publish, so its latest version from at or before this
     * one holds the step as it was then. Any other node (including a `loop`
     * node a person added themselves) returns null.
     *
     * @param  array<string, mixed>  $node
     * @return array{key: string, type: string, config: array<string, mixed>}|null
     */
    private function authoredLoopNode(array $node): ?array
    {
        $config = $node['config'] ?? [];

        if ($node['type'] !== FlowControlNodeType::Loop->value || ! is_string($config['workflow_id'] ?? null)) {
            return null;
        }

        $child = Workflow::withTrashed()
            ->where('id', $config['workflow_id'])
            ->where('is_internal', true)
            ->where('slug', Str::slug("loop-{$this->workflow_id}-{$node['key']}"))
            ->first();

        $childVersion = $child?->versions()
            ->where('created_at', '<=', $this->created_at)
            ->latest('version')
            ->first();

        $step = collect($childVersion?->graph['nodes'] ?? [])->firstWhere('key', 'item');

        if ($step === null) {
            return null;
        }

        return [
            'key' => $node['key'],
            'type' => $step['type'],
            'config' => [
                ...($step['config'] ?? []),
                '_loop' => Arr::only($config, ['items_path', 'max_concurrent', 'on_item_error']),
            ],
        ];
    }
}
