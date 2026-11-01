<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantMeetingPrepStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An upcoming meeting synced from the owner's calendar (`MeetingSync`).
 * `attendees` is a list of {email, name}, never including the owner.
 */
#[Fillable(['assistant_id', 'provider_event_id', 'title', 'starts_at', 'ends_at', 'attendees', 'is_external', 'html_link', 'description', 'prep_status'])]
class AssistantMeeting extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prep_status' => 'none',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attendees' => 'array',
            'is_external' => 'boolean',
            'prep_status' => AssistantMeetingPrepStatus::class,
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AssistantBriefingRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(AssistantBriefingRun::class)->latestOfMany();
    }
}
