<?php

namespace App\Actions\Workflows\Builder;

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class CreateWorkflowBuilderSessionAction
{
    public function execute(Workspace $workspace, User $user, ?string $title = null, ?Workflow $workflow = null): WorkflowBuilderSession
    {
        $graph = $workflow ? $this->graphFrom($workflow) : ['nodes' => [], 'edges' => []];

        $session = $workspace->builderSessions()->create([
            'user_id' => $user->id,
            'workflow_id' => $workflow?->id,
            'workflow_graph_hash' => $workflow?->graphFingerprint(),
            'title' => $title ?: ($workflow?->name ?? WorkflowBuilderSession::DEFAULT_TITLE),
            'draft_graph' => $graph,
            'last_activity_at' => now(),
        ]);

        // A starting point to restore to, however far the assistant takes
        // the draft from the workflow it was opened on.
        if ($workflow !== null) {
            $session->draftVersions()->create([
                'triggered_by' => $user->id,
                'graph_snapshot' => $graph,
                'label' => "Loaded from {$workflow->name}",
            ]);
        }

        return $session;
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function graphFrom(Workflow $workflow): array
    {
        $workflow->loadMissing(['nodes', 'edges']);
        $keysById = $workflow->nodes->pluck('key', 'id');

        return [
            'nodes' => $workflow->nodes->map(fn ($node) => [
                'key' => $node->key,
                'type' => $node->type,
                'config' => $node->config ?? [],
                'position' => $node->position,
            ])->values()->all(),
            'edges' => $workflow->edges->map(fn ($edge) => [
                'from' => $keysById[$edge->from_node_id],
                'to' => $keysById[$edge->to_node_id],
                'condition' => $edge->condition,
            ])->values()->all(),
        ];
    }
}
