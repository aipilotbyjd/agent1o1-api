<?php

namespace App\Services\Workflows;

use App\Authorization\WorkspaceContext;
use App\Enums\Workflows\PublishRequestStatus;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Exceptions\WorkflowValidationException;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Workflows\PublishRequestDecidedNotification;
use App\Notifications\Workflows\PublishReviewRequestedNotification;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Review before publishing: in a workspace that requires it
 * (`Workspace::requiresPublishApproval()`), a draft goes live only when
 * someone other than its requester approves it. The draft is copied into the
 * request, so approving publishes exactly what was reviewed however the draft
 * has moved on since. One request is open per workflow at a time; asking
 * again replaces the open one.
 */
class PublishRequests
{
    public function __construct(
        private readonly GraphValidator $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws WorkflowValidationException
     */
    public function request(Workflow $workflow, User $requester, ?string $notes): WorkflowPublishRequest
    {
        $workspace = $workflow->workspace;

        abort_unless($workspace->requiresPublishApproval(), 409, 'This workspace publishes changes directly, so there is nothing to request.');
        abort_if(
            $workflow->isPublished() && ! $workflow->has_unpublished_changes,
            409,
            "There's nothing to review: the draft is the same as the live version.",
        );

        $graph = $workflow->draftGraph();
        $errors = $this->validator->issues($graph['nodes'], $graph['edges'], $workflow->workspace_id);

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        $publishRequest = DB::transaction(function () use ($workflow, $requester, $notes, $graph): WorkflowPublishRequest {
            $workflow->publishRequests()
                ->where('status', PublishRequestStatus::Pending)
                ->lockForUpdate()
                ->update(['status' => PublishRequestStatus::Superseded]);

            return $workflow->publishRequests()->create([
                'workspace_id' => $workflow->workspace_id,
                'requested_by' => $requester->id,
                'status' => PublishRequestStatus::Pending,
                'notes' => filled($notes) ? $notes : null,
                'graph' => $graph,
                'editor_graph' => $workflow->editorGraph(),
                'graph_fingerprint' => $workflow->graphFingerprint(),
                'base_version_id' => $workflow->current_version_id,
            ]);
        });

        $this->audit->record($workspace->id, AuditAction::PublishRequestCreated, $publishRequest, [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
        ]);

        Notification::send(
            $this->approvers($workspace)->reject(fn (User $user): bool => $user->is($requester)),
            new PublishReviewRequestedNotification($publishRequest),
        );

        return $publishRequest;
    }

    /**
     * Publishes the reviewed copy as the next version. If it no longer passes
     * validation (say a step type was retired meanwhile) nothing changes and
     * the request stays open, so the reviewer can reject it with a reason.
     *
     * @throws WorkflowValidationException
     */
    public function approve(WorkflowPublishRequest $publishRequest, User $reviewer, ?string $reviewNotes): WorkflowPublishRequest
    {
        $this->ensureCanReview($publishRequest, $reviewer);

        $publishRequest = DB::transaction(function () use ($publishRequest, $reviewer, $reviewNotes): WorkflowPublishRequest {
            $locked = $this->lockPending($publishRequest);
            $workflow = $locked->workflow;

            $version = $workflow->publishGraph(
                $locked->graph,
                $locked->editor_graph,
                $locked->notes,
                $locked->requester ?? $reviewer,
            );

            // The draft may have moved on while this was in review; what went
            // live is the reviewed copy, so those later edits are still unpublished.
            if ($workflow->graphFingerprint() !== $locked->graph_fingerprint) {
                $workflow->forceFill(['has_unpublished_changes' => true])->save();
            }

            $locked->forceFill([
                'status' => PublishRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? $reviewNotes : null,
                'published_version_id' => $version->id,
            ])->save();

            return $locked;
        });

        $this->decided($publishRequest, $reviewer, AuditAction::PublishRequestApproved);

        return $publishRequest;
    }

    public function reject(WorkflowPublishRequest $publishRequest, User $reviewer, ?string $reviewNotes): WorkflowPublishRequest
    {
        $this->ensureCanReview($publishRequest, $reviewer);

        $publishRequest = DB::transaction(function () use ($publishRequest, $reviewer, $reviewNotes): WorkflowPublishRequest {
            $locked = $this->lockPending($publishRequest);

            $locked->forceFill([
                'status' => PublishRequestStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? $reviewNotes : null,
            ])->save();

            return $locked;
        });

        $this->decided($publishRequest, $reviewer, AuditAction::PublishRequestRejected);

        return $publishRequest;
    }

    public function withdraw(WorkflowPublishRequest $publishRequest, User $user): WorkflowPublishRequest
    {
        abort_unless($publishRequest->requested_by === $user->id, 403, 'Only the person who asked for this review can withdraw it.');

        $publishRequest = DB::transaction(function () use ($publishRequest): WorkflowPublishRequest {
            $locked = $this->lockPending($publishRequest);
            $locked->forceFill(['status' => PublishRequestStatus::Withdrawn])->save();

            return $locked;
        });

        $this->audit->record($publishRequest->workspace_id, AuditAction::PublishRequestWithdrawn, $publishRequest, [
            'workflow_id' => $publishRequest->workflow_id,
        ]);

        return $publishRequest;
    }

    public function canReview(WorkflowPublishRequest $publishRequest, User $user): bool
    {
        if ($publishRequest->requested_by === $user->id) {
            return false;
        }

        return WorkspaceContext::resolveRole($publishRequest->workspace, $user)?->has(Permission::WorkflowApprovePublish) ?? false;
    }

    /**
     * Everyone whose role lets them approve a change in this workspace.
     *
     * @return Collection<int, User>
     */
    public function approvers(Workspace $workspace): Collection
    {
        return $workspace->users()
            ->whereNull((new WorkspaceMember)->qualifyColumn('deleted_at'))
            ->get()
            ->filter(function (User $user) use ($workspace): bool {
                $role = $workspace->owner_id === $user->id ? Role::Owner : Role::tryFrom((string) $user->pivot?->role);

                return $role?->has(Permission::WorkflowApprovePublish) ?? false;
            })
            ->values();
    }

    private function ensureCanReview(WorkflowPublishRequest $publishRequest, User $reviewer): void
    {
        abort_if(
            $publishRequest->requested_by === $reviewer->id,
            403,
            "You can't review your own request. Ask another admin, or withdraw it.",
        );
        abort_unless($this->canReview($publishRequest, $reviewer), 403, 'Only workspace admins and owners can review changes.');
    }

    private function lockPending(WorkflowPublishRequest $publishRequest): WorkflowPublishRequest
    {
        $locked = WorkflowPublishRequest::query()->lockForUpdate()->findOrFail($publishRequest->id);

        abort_unless($locked->isPending(), 409, match ($locked->status) {
            PublishRequestStatus::Approved => 'This request was already approved.',
            PublishRequestStatus::Rejected => 'This request was already rejected.',
            PublishRequestStatus::Withdrawn => 'This request was withdrawn.',
            default => 'A newer request replaced this one.',
        });

        return $locked;
    }

    private function decided(WorkflowPublishRequest $publishRequest, User $reviewer, AuditAction $action): void
    {
        $this->audit->record($publishRequest->workspace_id, $action, $publishRequest, [
            'workflow_id' => $publishRequest->workflow_id,
            'published_version_id' => $publishRequest->published_version_id,
        ]);

        $requester = $publishRequest->requester;

        if ($requester !== null && ! $requester->is($reviewer)) {
            $requester->notify(new PublishRequestDecidedNotification($publishRequest));
        }
    }
}
