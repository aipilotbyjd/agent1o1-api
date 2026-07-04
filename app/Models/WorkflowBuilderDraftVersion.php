<?php

namespace App\Models;

use Database\Factories\WorkflowBuilderDraftVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id',
    'triggered_by',
    'nodes_snapshot',
    'edges_snapshot',
    'label',
])]
class WorkflowBuilderDraftVersion extends Model
{
    /** @use HasFactory<WorkflowBuilderDraftVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'nodes_snapshot' => 'array',
            'edges_snapshot' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderMessage::class, 'triggered_by');
    }
}
