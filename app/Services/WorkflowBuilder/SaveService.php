<?php

namespace App\Services\WorkflowBuilder;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowService;

class SaveService
{
    public function __construct(
        private readonly ValidationService $validationService,
        private readonly WorkflowService $workflowService,
    ) {}

    /**
     * Validate then persist the draft as a real Workflow (or a new version of an existing one).
     *
     * @return array{workflow: Workflow, errors: array<int, array{node_id: string|null, issue: string}>}
     */
    public function save(WorkflowBuilderSession $session, User $user): array
    {
        $errors = $this->validationService->validate($session);

        if (! empty($errors)) {
            return ['workflow' => null, 'errors' => $errors];
        }

        $definition = [
            'name' => $session->title,
            'nodes' => $session->nodes_draft ?? [],
            'edges' => $session->edges_draft ?? [],
        ];

        if ($session->workflow_id) {
            $workflow = $session->workflow;
            $workflow->update(['name' => $session->title]);
            $this->workflowService->createVersion($workflow, $definition);
            $workflow = $workflow->fresh(['currentVersion']);
        } else {
            $workflow = $this->workflowService->create($session->workspace, $user, $definition);
        }

        $session->markCompleted($workflow->id);

        return ['workflow' => $workflow, 'errors' => []];
    }
}
