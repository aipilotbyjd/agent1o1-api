<?php

namespace App\Models\Workflows;

use App\Enums\Workflows\PublishRequestStatus;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to put a workflow's draft live in a workspace that requires
 * approval to publish: see `Workspace::requiresPublishApproval()`. Not to be
 * confused with `WorkflowApproval`, which pauses a single run at a
 * HumanApproval step.
 *
 * The draft is copied in (`graph`, `editor_graph`) when the request is made,
 * so the author can keep editing and the reviewer approves exactly what they
 * were shown. `graph_fingerprint` is `Workflow::graphFingerprint()` at that
 * moment, to tell whether the draft has moved on since; `base_version_id` is
 * the version that was live then, to tell whether that has.
 */
#[Fillable(['workspace_id', 'workflow_id', 'requested_by', 'status', 'notes', 'graph', 'editor_graph', 'graph_fingerprint', 'base_version_id'])]
class WorkflowPublishRequest extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishRequestStatus::class,
            'graph' => 'array',
            'editor_graph' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'base_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'published_version_id');
    }

    public function isPending(): bool
    {
        return $this->status === PublishRequestStatus::Pending;
    }
}
