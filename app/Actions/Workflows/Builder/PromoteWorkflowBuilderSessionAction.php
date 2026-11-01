<?php

namespace App\Actions\Workflows\Builder;

use App\Enums\Billing\PlanLimit;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Exceptions\WorkflowValidationException;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a session's `draft_graph` as a real `Workflow`'s draft. The draft
 * shape already matches `Workflow::replaceGraph()`'s expected input exactly
 * (see `WorkflowBuilderSession`'s docblock), so promoting needs no
 * transform, just wiring up which workflow the graph lands on. Publishing
 * a version of that workflow is a separate step.
 *
 * All of it is one transaction with the session row locked: two promotes
 * can't both create a workflow, and a draft the workflow refuses leaves no
 * half-made workflow behind.
 */
class PromoteWorkflowBuilderSessionAction
{
    public function __construct(private readonly PlanLimitGate $limits) {}

    /**
     * @param  bool  $overwrite  Replace the workflow's draft even if it was edited outside this session.
     *
     * @throws WorkflowBuilderConflictException
     * @throws WorkflowValidationException
     */
    public function execute(WorkflowBuilderSession $session, User $by, ?string $name = null, bool $overwrite = false): Workflow
    {
        return DB::transaction(function () use ($session, $by, $name, $overwrite): Workflow {
            $session = WorkflowBuilderSession::query()->lockForUpdate()->findOrFail($session->id);

            $session->assertEditable();

            // A turn still running is mid-way through editing the draft.
            $session->failStaleReplies();

            if ($session->hasReplyInFlight()) {
                throw WorkflowBuilderConflictException::replyInProgress();
            }

            $workflow = $session->workflow_id !== null
                ? Workflow::query()->lockForUpdate()->find($session->workflow_id)
                : null;

            if ($workflow === null) {
                // Re-promoting into the workflow this session already owns
                // isn't a new resource, so only the first promotion is
                // charged against the cap.
                $this->limits->assertCanCreate($session->workspace, PlanLimit::Workflows);

                $workflow = $session->workspace->workflows()->create([
                    'name' => $name ?: $session->title,
                    'slug' => (Str::slug($name ?: $session->title) ?: 'workflow').'-'.Str::random(6),
                    'created_by' => $by->id,
                ]);
            } elseif (! $overwrite && $session->workflow_graph_hash !== null && $workflow->graphFingerprint() !== $session->workflow_graph_hash) {
                throw WorkflowBuilderConflictException::workflowChanged();
            }

            $workflow->replaceGraph($session->currentGraph());

            $session->update([
                'workflow_id' => $workflow->id,
                'workflow_graph_hash' => $workflow->graphFingerprint(),
                'status' => BuilderSessionStatus::Promoted,
            ]);

            return $workflow;
        });
    }
}
