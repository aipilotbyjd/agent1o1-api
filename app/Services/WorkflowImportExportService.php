<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;

class WorkflowImportExportService
{
    private const FORMAT_VERSION = 1;

    public function __construct(private readonly WorkflowService $workflowService) {}

    /**
     * Export a workflow to a portable, self-describing array.
     *
     * @return array<string, mixed>
     */
    public function export(Workflow $workflow): array
    {
        $version = $workflow->currentVersion;

        return [
            'format_version' => self::FORMAT_VERSION,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'icon' => $workflow->icon,
            'color' => $workflow->color,
            'nodes' => $version?->nodes_data ?? [],
            'edges' => $version?->edges_data ?? [],
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Import a previously exported workflow into a workspace.
     *
     * @param  array<string, mixed>  $payload
     */
    public function import(Workspace $workspace, User $user, array $payload): Workflow
    {
        return $this->workflowService->create($workspace, $user, [
            'name' => ($payload['name'] ?? 'Imported workflow').' (imported)',
            'description' => $payload['description'] ?? null,
            'icon' => $payload['icon'] ?? null,
            'color' => $payload['color'] ?? null,
            'nodes' => $payload['nodes'] ?? [],
            'edges' => $payload['edges'] ?? [],
        ]);
    }
}
