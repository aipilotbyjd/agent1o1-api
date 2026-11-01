<?php

namespace App\Models\Workspaces;

use Database\Factories\Workspaces\WorkspaceDataEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One remembered value in a workspace's Data Store: `{store, key} => value`,
 * written and read by the Data Store workflow node. A row whose `expires_at`
 * has passed is treated as absent.
 */
#[Fillable(['workspace_id', 'store', 'key', 'value', 'expires_at'])]
class WorkspaceDataEntry extends Model
{
    /** @use HasFactory<WorkspaceDataEntryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Rows that have not expired.
     *
     * @param  Builder<WorkspaceDataEntry>  $query
     * @return Builder<WorkspaceDataEntry>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
