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
    'version_number',
    'nodes_data',
    'edges_data',
    'is_published',
    'published_at',
    'published_by',
])]
class WorkflowVersion extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'nodes_data' => 'array',
            'edges_data' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
