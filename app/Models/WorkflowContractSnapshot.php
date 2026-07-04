<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'workflow_id',
    'version_id',
    'created_by',
    'input_schema',
    'output_schema',
    'node_signature',
])]
class WorkflowContractSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'output_schema' => 'array',
            'node_signature' => 'array',
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
     * @return HasMany<WorkflowContractTestRun, $this>
     */
    public function testRuns(): HasMany
    {
        return $this->hasMany(WorkflowContractTestRun::class, 'contract_id');
    }
}
