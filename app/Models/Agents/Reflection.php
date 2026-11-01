<?php

namespace App\Models\Agents;

use App\Enums\Agents\ReflectionStatus;
use App\Enums\Agents\ReflectionType;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\ReflectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One proposed improvement: see the migration's docblock. `status` and
 * `applied_run_id` are engine-managed: written by
 * `Services\Agents\ReflectionApplier` via `forceFill()`, never mass-assigned,
 * so a client can't mark its own suggestion "applied" without the apply
 * actually having happened.
 */
#[Fillable([
    'workspace_id', 'agent_id', 'reflection_run_id', 'type', 'title', 'rationale',
    'evidence', 'confidence', 'support_count', 'proposed_prompt', 'target_skill_id',
])]
class Reflection extends Model
{
    /** @use HasFactory<ReflectionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'confidence' => 0,
        'support_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReflectionType::class,
            'status' => ReflectionStatus::class,
            'evidence' => 'array',
            'confidence' => 'integer',
            'support_count' => 'integer',
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

    public function reflectionRun(): BelongsTo
    {
        return $this->belongsTo(ReflectionRun::class);
    }

    public function targetSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'target_skill_id');
    }

    public function appliedRun(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'applied_run_id');
    }
}
