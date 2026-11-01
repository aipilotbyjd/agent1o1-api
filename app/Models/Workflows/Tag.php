<?php

namespace App\Models\Workflows;

use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['workspace_id', 'name', 'color'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUuids;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'tag_workflow');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'tag_agent');
    }
}
