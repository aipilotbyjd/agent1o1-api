<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_id',
    'workspace_id',
    'created_by',
    'token',
    'allow_clone',
    'expires_at',
    'view_count',
])]
class WorkflowShare extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'allow_clone' => 'boolean',
            'expires_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
