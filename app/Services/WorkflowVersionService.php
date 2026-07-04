<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

class WorkflowVersionService
{
    /**
     * Publish a version: mark it published and make it the workflow's current version.
     */
    public function publish(Workflow $workflow, WorkflowVersion $version, User $user): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow, $version, $user) {
            $workflow->versions()->update(['is_published' => false]);

            $version->update([
                'is_published' => true,
                'published_at' => now(),
                'published_by' => $user->id,
            ]);

            $workflow->update(['current_version_id' => $version->id]);

            return $version->fresh('publisher');
        });
    }

    /**
     * Roll back to an earlier version by cloning it as a new latest version.
     */
    public function rollback(Workflow $workflow, WorkflowVersion $version): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow, $version) {
            $next = ($workflow->versions()->max('version_number') ?? 0) + 1;

            $clone = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'workspace_id' => $workflow->workspace_id,
                'version_number' => $next,
                'nodes_data' => $version->nodes_data,
                'edges_data' => $version->edges_data,
            ]);

            $workflow->update(['current_version_id' => $clone->id]);

            return $clone;
        });
    }

    /**
     * Compute a structural diff between two versions.
     *
     * @return array{added: list<string>, removed: list<string>, changed: list<string>, edges_changed: bool}
     */
    public function diff(WorkflowVersion $from, WorkflowVersion $to): array
    {
        $fromNodes = $this->keyById($from->nodes_data ?? []);
        $toNodes = $this->keyById($to->nodes_data ?? []);

        $added = array_values(array_diff(array_keys($toNodes), array_keys($fromNodes)));
        $removed = array_values(array_diff(array_keys($fromNodes), array_keys($toNodes)));

        $changed = [];
        foreach (array_intersect(array_keys($fromNodes), array_keys($toNodes)) as $id) {
            if ($fromNodes[$id] !== $toNodes[$id]) {
                $changed[] = $id;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'edges_changed' => ($from->edges_data ?? []) !== ($to->edges_data ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function keyById(array $nodes): array
    {
        $keyed = [];

        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $keyed[$node['id']] = $node;
            }
        }

        return $keyed;
    }
}
