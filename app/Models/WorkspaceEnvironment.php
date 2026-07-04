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
    'name',
    'slug',
    'variables',
    'is_default',
])]
class WorkspaceEnvironment extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<WorkflowEnvironmentRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(WorkflowEnvironmentRelease::class, 'environment_id');
    }
}
