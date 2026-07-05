<?php

namespace App\Models;

use App\Enums\BuilderSessionStatus;
use Database\Factories\WorkflowBuilderSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'user_id',
    'workflow_id',
    'conversation_id',
    'title',
    'nodes_draft',
    'edges_draft',
    'draft_lock_version',
    'status',
    'last_activity_at',
])]
class WorkflowBuilderSession extends Model
{
    /** @use HasFactory<WorkflowBuilderSessionFactory> */
    use HasFactory, HasUuids;

    /**
     * Mirror the DB default in memory. Eloquent does not hydrate database
     * defaults onto a freshly created model, so without this the optimistic
     * draft lock reads null and its "WHERE draft_lock_version = NULL" guard
     * matches nothing — making the first draft mutation always conflict.
     */
    protected $attributes = [
        'draft_lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'nodes_draft' => 'array',
            'edges_draft' => 'array',
            'status' => BuilderSessionStatus::class,
            'last_activity_at' => 'datetime',
            'draft_lock_version' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WorkflowBuilderMessage::class, 'session_id')->oldest();
    }

    public function draftVersions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderDraftVersion::class, 'session_id')->latest();
    }

    public function versions(): HasMany
    {
        return $this->draftVersions();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', BuilderSessionStatus::Active);
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->status === BuilderSessionStatus::Active;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function markCompleted(string $workflowId): void
    {
        $this->update([
            'status' => BuilderSessionStatus::Completed,
            'workflow_id' => $workflowId,
        ]);
    }

    public function markArchived(): void
    {
        $this->update(['status' => BuilderSessionStatus::Archived]);
    }
}
