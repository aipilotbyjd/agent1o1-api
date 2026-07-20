<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'agent_id',
    'agent_run_id',
    'conversation_id',
    'created_by',
    'group_id',
    'version',
    'filename',
    'mime_type',
    'size',
    'disk',
    'path',
    'metadata',
])]
class Artifact extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'metadata' => 'array',
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
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * All versions sharing this artifact's group_id, newest first.
     *
     * @return HasMany<Artifact, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'group_id', 'group_id')->orderByDesc('version');
    }

    /**
     * Restrict to only the newest version within each group_id.
     */
    public function scopeLatestPerGroup(Builder $query): Builder
    {
        return $query->joinSub(
            static::query()->selectRaw('group_id, MAX(version) as max_version')->groupBy('group_id'),
            'latest_versions',
            function ($join) {
                $join->on('artifacts.group_id', '=', 'latest_versions.group_id')
                    ->on('artifacts.version', '=', 'latest_versions.max_version');
            }
        );
    }

    public function isPreviewable(): bool
    {
        return str_starts_with($this->mime_type, 'image/') || $this->mime_type === 'text/html';
    }
}
