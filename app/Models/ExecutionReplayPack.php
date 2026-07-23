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
    'execution_id',
    'created_by',
    'label',
    'version_snapshot',
    'trigger_data',
])]
class ExecutionReplayPack extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'version_snapshot' => 'array',
            'trigger_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
