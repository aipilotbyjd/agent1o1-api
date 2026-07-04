<?php

namespace App\Models;

use App\Enums\BuilderMessageRole;
use Database\Factories\WorkflowBuilderMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'session_id',
    'draft_version_id',
    'role',
    'content',
    'actions',
    'processing_status',
    'error_message',
])]
class WorkflowBuilderMessage extends Model
{
    /** @use HasFactory<WorkflowBuilderMessageFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'role' => BuilderMessageRole::class,
            'actions' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    public function draftVersion(): HasOne
    {
        return $this->hasOne(WorkflowBuilderDraftVersion::class, 'triggered_by');
    }

    public function isPending(): bool
    {
        return $this->processing_status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->processing_status === 'failed';
    }
}
