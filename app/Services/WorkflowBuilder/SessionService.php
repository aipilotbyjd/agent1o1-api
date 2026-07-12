<?php

namespace App\Services\WorkflowBuilder;

use App\Enums\BuilderSessionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;

class SessionService
{
    public function __construct(private readonly DraftService $draftService) {}

    public function create(Workspace $workspace, User $user, array $data): WorkflowBuilderSession
    {
        $session = WorkflowBuilderSession::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => $data['title'] ?? 'Untitled workflow',
            'status' => BuilderSessionStatus::Active,
            'nodes_draft' => $data['nodes'] ?? [],
            'edges_draft' => $data['edges'] ?? [],
            'draft_lock_version' => 0,
            'last_activity_at' => now(),
        ]);

        if (isset($data['workflow_id'])) {
            $session = $this->seedFromWorkflow($session, $data['workflow_id']);
        }

        return $session;
    }

    public function rename(WorkflowBuilderSession $session, string $title): WorkflowBuilderSession
    {
        $session->update(['title' => $title]);

        return $session->fresh();
    }

    public function archive(WorkflowBuilderSession $session): void
    {
        $session->markArchived();
        $session->messages()->delete();
    }

    private function seedFromWorkflow(WorkflowBuilderSession $session, string $workflowId): WorkflowBuilderSession
    {
        $workflow = Workflow::find($workflowId);
        $version = $workflow?->currentVersion;

        if (! $version) {
            return $session;
        }

        $nodes = $this->normalizeNodes($version->nodes_data ?? []);
        $edges = $this->normalizeEdges($version->edges_data ?? []);

        $session->update([
            'workflow_id' => $workflowId,
            'nodes_draft' => $nodes,
            'edges_draft' => $edges,
        ]);

        // Initial snapshot so user can always restore back to the original
        $this->draftService->applyBulk(
            $session->fresh(),
            $nodes,
            $edges,
            null,
            'Loaded from workflow'
        );

        return $session->fresh();
    }

    /**
     * Normalize workflow-version nodes into the builder draft shape
     * ({id, type, name, config, position}). Versions may be stored in canvas
     * format ({id, type, position, data:{defKey,label,values}}) by the frontend
     * autosave or already in builder format by the builder's own save path.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNodes(array $nodes): array
    {
        return array_values(array_map(function ($node) {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            return [
                'id' => $node['id'] ?? 'node_'.uniqid(),
                'type' => $node['type'] ?? ($data['defKey'] ?? 'unknown'),
                'name' => $node['name'] ?? $data['label'] ?? $node['type'] ?? 'Node',
                'config' => (array) ($node['config'] ?? $data['values'] ?? []),
                'position' => (array) ($node['position'] ?? ['x' => 0, 'y' => 200]),
            ];
        }, $nodes));
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEdges(array $edges): array
    {
        return array_values(array_map(fn ($edge) => [
            'source' => $edge['source'] ?? '',
            'target' => $edge['target'] ?? '',
            'sourceHandle' => $edge['sourceHandle'] ?? $edge['source_handle'] ?? 'output',
            'targetHandle' => $edge['targetHandle'] ?? $edge['target_handle'] ?? 'input',
        ], $edges));
    }

    public function cleanupIdle(int $days = 30): int
    {
        return WorkflowBuilderSession::query()
            ->where('status', BuilderSessionStatus::Active)
            ->whereNull('workflow_id')
            ->where('last_activity_at', '<', now()->subDays($days))
            ->update(['status' => BuilderSessionStatus::Archived]);
    }
}
