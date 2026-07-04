<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'workflow_id',
    'environment_id',
    'version_id',
    'released_by',
    'notes',
    'released_at',
])]
class WorkflowEnvironmentRelease extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkspaceEnvironment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(WorkspaceEnvironment::class, 'environment_id');
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
