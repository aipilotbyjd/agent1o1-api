<?php

namespace App\Models\Workflows\Builder;

use App\Enums\Workflows\BuilderMessageMode;
use App\Enums\Workflows\BuilderMessageStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Artifacts\Artifact;
use App\Models\User;
use Database\Factories\Workflows\Builder\WorkflowBuilderMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['session_id', 'user_id', 'draft_version_id', 'role', 'mode', 'context', 'model_catalog_id', 'content', 'actions', 'processing_status', 'error_message'])]
class WorkflowBuilderMessage extends Model
{
    /** @use HasFactory<WorkflowBuilderMessageFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'processing_status' => 'completed',
        'mode' => 'build',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'context' => 'array',
            'processing_status' => BuilderMessageStatus::class,
            'mode' => BuilderMessageMode::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    /**
     * Who sent a user message: null on assistant messages.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model the sender picked for this turn, if any.
     */
    public function modelCatalog(): BelongsTo
    {
        return $this->belongsTo(ModelCatalog::class, 'model_catalog_id');
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderDraftVersion::class, 'draft_version_id');
    }

    /**
     * Files a member sent along with this (user) message. Stored as
     * `Artifact`s and handed to the model with the message on every turn:
     * see `ProcessWorkflowBuilderMessageJob` and `WorkflowBuilderAgent::messages()`.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }
}
