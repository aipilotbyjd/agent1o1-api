<?php

namespace App\Models\Agents;

use App\Enums\Agents\ReflectionApplyBehavior;
use Database\Factories\Agents\ReflectionSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether/how one `Agent` periodically reviews its own past `AgentSession`s:
 * see `Services\Agents\ReflectionAnalyzer`. `last_run_at`/`next_run_at` are
 * engine-managed (not in `#[Fillable]`) written by
 * `Console\Commands\Agents\RunDueReflectionsCommand` and
 * `ReflectionAnalyzer`, the same "the schedule can't be forged through the
 * settings form" reasoning as `Trigger.last_run_at`.
 */
#[Fillable(['agent_id', 'is_enabled', 'apply_behavior', 'schedule_cron', 'min_chats_threshold', 'extra_instructions', 'notify_on_skip'])]
class ReflectionSettings extends Model
{
    /** @use HasFactory<ReflectionSettingsFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => false,
        'apply_behavior' => 'review_queue',
        'schedule_cron' => '0 22 * * *',
        'min_chats_threshold' => 5,
        'notify_on_skip' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'apply_behavior' => ReflectionApplyBehavior::class,
            'min_chats_threshold' => 'integer',
            'notify_on_skip' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
