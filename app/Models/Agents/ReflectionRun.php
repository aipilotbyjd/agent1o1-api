<?php

namespace App\Models\Agents;

use App\Enums\Agents\ReflectionRunStatus;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\ReflectionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One execution of `Services\Agents\ReflectionAnalyzer` over an agent's
 * recent conversation history. `status`/counters/timestamps are
 * engine-managed (written by `ReflectionAnalyzer` via `forceFill()`, not
 * mass-assigned) same convention as `AgentEvalRun`.
 */
#[Fillable(['workspace_id', 'agent_id'])]
class ReflectionRun extends Model
{
    /** @use HasFactory<ReflectionRunFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'sessions_analyzed_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReflectionRunStatus::class,
            'sessions_analyzed_count' => 'integer',
            'usage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class);
    }

    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }
}
