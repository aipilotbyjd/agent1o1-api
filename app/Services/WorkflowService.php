<?php

namespace App\Services;

use App\Engine\WorkflowGraph;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function create(Workspace $workspace, User $creator, array $data): Workflow
    {
        return DB::transaction(function () use ($workspace, $creator, $data) {
            $workflow = Workflow::create([
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'folder_id' => $data['folder_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
            ]);

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'workspace_id' => $workspace->id,
                'version_number' => 1,
                'nodes_data' => $data['nodes'] ?? [],
                'edges_data' => $data['edges'] ?? [],
            ]);

            $workflow->update(['current_version_id' => $version->id]);

            if (! empty($data['tags'])) {
                $workflow->tags()->sync($data['tags']);
            }

            return $workflow->fresh(['currentVersion', 'tags']);
        });
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow->update(collect($data)->only([
                'name', 'description', 'icon', 'color', 'folder_id', 'error_workflow_id', 'max_concurrent_executions', 'is_favorite',
            ])->all());

            // Graph changes create a new version
            if (isset($data['nodes']) || isset($data['edges'])) {
                $this->createVersion($workflow, $data);
            }

            if (isset($data['tags'])) {
                $workflow->tags()->sync($data['tags']);
            }

            return $workflow->fresh(['currentVersion', 'tags']);
        });
    }

    public function createVersion(Workflow $workflow, array $data): WorkflowVersion
    {
        $latest = $workflow->versions()->max('version_number') ?? 0;
        $current = $workflow->currentVersion;

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'version_number' => $latest + 1,
            'nodes_data' => $data['nodes'] ?? $current?->nodes_data ?? [],
            'edges_data' => $data['edges'] ?? $current?->edges_data ?? [],
        ]);

        $workflow->update(['current_version_id' => $version->id]);

        return $version;
    }

    public function activate(Workflow $workflow): Workflow
    {
        // Validate the graph compiles before going live
        $version = $workflow->currentVersion;

        if (! $version || empty($version->nodes_data)) {
            throw new \InvalidArgumentException('Workflow has no nodes — cannot activate.');
        }

        WorkflowGraph::compile($version->nodes_data, $version->edges_data ?? []);

        $workflow->update(['is_active' => true]);
        $workflow->triggers()->update(['is_active' => true]);

        return $workflow->fresh();
    }

    public function deactivate(Workflow $workflow): Workflow
    {
        $workflow->update(['is_active' => false]);
        $workflow->triggers()->update(['is_active' => false]);

        return $workflow->fresh();
    }

    public function duplicate(Workflow $workflow, User $user): Workflow
    {
        return DB::transaction(function () use ($workflow, $user) {
            $copy = $workflow->replicate(['execution_count', 'last_executed_at', 'success_rate', 'current_version_id']);
            $copy->name = "{$workflow->name} (copy)";
            $copy->is_active = false;
            $copy->created_by = $user->id;
            $copy->save();

            if ($version = $workflow->currentVersion) {
                $newVersion = WorkflowVersion::create([
                    'workflow_id' => $copy->id,
                    'workspace_id' => $copy->workspace_id,
                    'version_number' => 1,
                    'nodes_data' => $version->nodes_data,
                    'edges_data' => $version->edges_data,
                ]);

                $copy->update(['current_version_id' => $newVersion->id]);
            }

            $copy->tags()->sync($workflow->tags->pluck('id'));

            return $copy->fresh(['currentVersion', 'tags']);
        });
    }

    public function delete(Workflow $workflow): void
    {
        $workflow->delete();
    }
}
