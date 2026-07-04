<?php

namespace App\Services\WorkflowBuilder;

use App\Exceptions\DraftConflictException;
use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use Illuminate\Support\Facades\DB;

class DraftService
{
    /**
     * @param  array{id: string, type: string, name: string, config: array, position: array{x: int, y: int}}  $node
     */
    public function addNode(WorkflowBuilderSession $session, array $node, ?WorkflowBuilderMessage $triggeredBy = null): void
    {
        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($node, $triggeredBy) {
            $nodes = $locked->nodes_draft ?? [];
            $nodes[] = $node;
            $locked->nodes_draft = $nodes;
            $locked->save();
            $this->snapshot($locked, $triggeredBy, "Added node: {$node['name']}");
        });
    }

    public function removeNode(WorkflowBuilderSession $session, string $nodeId, ?WorkflowBuilderMessage $triggeredBy = null): void
    {
        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($nodeId, $triggeredBy) {
            $locked->nodes_draft = collect($locked->nodes_draft ?? [])
                ->reject(fn ($n) => ($n['id'] ?? '') === $nodeId)
                ->values()
                ->all();

            $locked->edges_draft = collect($locked->edges_draft ?? [])
                ->reject(fn ($e) => ($e['source'] ?? '') === $nodeId || ($e['target'] ?? '') === $nodeId)
                ->values()
                ->all();

            $locked->save();
            $this->snapshot($locked, $triggeredBy, "Removed node: {$nodeId}");
        });
    }

    /**
     * Deep-merges $changes into the node. Config is deep-merged, not replaced.
     */
    public function updateNode(WorkflowBuilderSession $session, string $nodeId, array $changes, ?WorkflowBuilderMessage $triggeredBy = null): bool
    {
        $found = false;

        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($nodeId, $changes, $triggeredBy, &$found) {
            $nodes = collect($locked->nodes_draft ?? [])->map(function ($node) use ($nodeId, $changes, &$found) {
                if (($node['id'] ?? '') !== $nodeId) {
                    return $node;
                }
                $found = true;

                if (isset($changes['config'])) {
                    $changes['config'] = array_merge_recursive(
                        (array) ($node['config'] ?? []),
                        (array) $changes['config']
                    );
                }

                return array_merge($node, $changes);
            })->all();

            $locked->nodes_draft = $nodes;
            $locked->save();

            if ($found) {
                $this->snapshot($locked, $triggeredBy, "Updated node: {$nodeId}");
            }
        });

        return $found;
    }

    /**
     * @param  array{source: string, target: string, sourceHandle?: string, targetHandle?: string}  $edge
     */
    public function addEdge(WorkflowBuilderSession $session, array $edge, ?WorkflowBuilderMessage $triggeredBy = null): void
    {
        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($edge, $triggeredBy) {
            $edges = $locked->edges_draft ?? [];
            $edges[] = $edge;
            $locked->edges_draft = $edges;
            $locked->save();
            $this->snapshot($locked, $triggeredBy, "Connected: {$edge['source']} → {$edge['target']}");
        });
    }

    public function removeEdge(WorkflowBuilderSession $session, string $source, string $target, ?WorkflowBuilderMessage $triggeredBy = null): void
    {
        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($source, $target, $triggeredBy) {
            $locked->edges_draft = collect($locked->edges_draft ?? [])
                ->reject(fn ($e) => ($e['source'] ?? '') === $source && ($e['target'] ?? '') === $target)
                ->values()
                ->all();

            $locked->save();
            $this->snapshot($locked, $triggeredBy, "Disconnected: {$source} → {$target}");
        });
    }

    public function restoreVersion(WorkflowBuilderSession $session, WorkflowBuilderDraftVersion $version): void
    {
        $label = 'Restored from '.($version->created_at?->format('M j, H:i') ?? 'snapshot');

        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($version, $label) {
            $locked->nodes_draft = $version->nodes_snapshot;
            $locked->edges_draft = $version->edges_snapshot;
            $locked->save();
            $this->snapshot($locked, null, $label);
        });
    }

    public function applyBulk(WorkflowBuilderSession $session, array $nodes, array $edges, ?WorkflowBuilderMessage $triggeredBy = null, string $label = 'AI generation'): void
    {
        $this->mutate($session, function (WorkflowBuilderSession $locked) use ($nodes, $edges, $triggeredBy, $label) {
            $locked->nodes_draft = $nodes;
            $locked->edges_draft = $edges;
            $locked->save();
            $this->snapshot($locked, $triggeredBy, $label);
        });
    }

    private function mutate(WorkflowBuilderSession $session, callable $mutation): void
    {
        DB::transaction(function () use ($session, $mutation) {
            $locked = WorkflowBuilderSession::query()
                ->whereKey($session->id)
                ->where('draft_lock_version', $session->draft_lock_version)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new DraftConflictException('Draft was modified by another request. Please refresh and try again.');
            }

            $mutation($locked);

            $locked->increment('draft_lock_version');
            $locked->touchActivity();

            // Sync the caller's in-memory version so they don't retry with the stale version
            $session->draft_lock_version = $locked->draft_lock_version;
        });

        $session->refresh();
    }

    private function snapshot(WorkflowBuilderSession $session, ?WorkflowBuilderMessage $triggeredBy, string $label): void
    {
        WorkflowBuilderDraftVersion::create([
            'session_id' => $session->id,
            'triggered_by' => $triggeredBy?->id,
            'nodes_snapshot' => $session->nodes_draft ?? [],
            'edges_snapshot' => $session->edges_draft ?? [],
            'label' => $label,
        ]);
    }
}
