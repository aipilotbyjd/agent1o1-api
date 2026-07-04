<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkflowTemplateService
{
    public function __construct(private readonly WorkflowService $workflowService) {}

    /**
     * Instantiate a workflow in a workspace from a template.
     */
    public function deployToWorkspace(WorkflowTemplate $template, Workspace $workspace, User $creator): Workflow
    {
        return DB::transaction(function () use ($template, $workspace, $creator) {
            $workflow = $this->workflowService->create($workspace, $creator, [
                'name' => $template->name,
                'description' => $template->description,
                'icon' => $template->icon,
                'color' => $template->color,
                'nodes' => $template->nodes_data,
                'edges' => $template->edges_data,
            ]);

            $template->increment('usage_count');

            return $workflow;
        });
    }
}
