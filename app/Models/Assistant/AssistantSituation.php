<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantSituationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece of real work the owner owns, found by a report, with steps the
 * assistant could take. "Send to assistant" turns it into a conversation.
 */
#[Fillable(['assistant_id', 'assistant_briefing_run_id', 'title', 'summary', 'next_step', 'sources', 'status', 'assistant_session_id'])]
class AssistantSituation extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantSituationStatus::class,
            'sources' => 'array',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AssistantBriefingRun::class, 'assistant_briefing_run_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AssistantSituationStep::class)->orderBy('position');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }
}
