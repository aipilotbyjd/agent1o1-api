<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantBriefingRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One report. `run_key` (`daily:2026-10-03`, `manual:<uuid>`) is unique per
 * config, so a retried or doubled schedule tick can never produce two.
 */
#[Fillable(['assistant_briefing_config_id', 'assistant_meeting_id', 'run_key', 'status', 'trigger', 'window_start', 'window_end', 'summary', 'document', 'source_results', 'delivery_results', 'usage', 'error', 'delivered_at'])]
class AssistantBriefingRun extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantBriefingRunStatus::class,
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'source_results' => 'array',
            'delivery_results' => 'array',
            'usage' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AssistantBriefingConfig::class, 'assistant_briefing_config_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(AssistantMeeting::class, 'assistant_meeting_id');
    }

    public function situations(): HasMany
    {
        return $this->hasMany(AssistantSituation::class);
    }
}
