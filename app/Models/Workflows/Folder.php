<?php

namespace App\Models\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'type', 'parent_id', 'name', 'color', 'position'])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TriggerTargetType::class,
            'position' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('position');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
