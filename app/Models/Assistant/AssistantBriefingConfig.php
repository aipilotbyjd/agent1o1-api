<?php

namespace App\Models\Assistant;

use App\Enums\Assistant\AssistantBriefingType;
use App\Services\Timezones\Timezones;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The owner's settings for one background report (the Daily report now,
 * Meeting Prep next): when it runs, which apps it reads, what to stress,
 * and where it is delivered besides the app.
 *
 * `schedule`: {time: "HH:MM", days: [1..7] (ISO, Monday = 1), timezone}.
 * `delivery`: {email: bool}.
 */
#[Fillable(['assistant_id', 'type', 'enabled', 'paused_at', 'schedule', 'connector_scope', 'connector_keys', 'instructions', 'delivery', 'settings'])]
class AssistantBriefingConfig extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssistantBriefingType::class,
            'enabled' => 'boolean',
            'paused_at' => 'datetime',
            'schedule' => 'array',
            'connector_keys' => 'array',
            'delivery' => 'array',
            'settings' => 'array',
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

    public function cursors(): HasMany
    {
        return $this->hasMany(AssistantSourceCursor::class);
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->paused_at === null;
    }

    public function timezone(): string
    {
        return Timezones::resolve($this->schedule['timezone'] ?? null);
    }

    /**
     * @return list<int>
     */
    public function days(): array
    {
        return array_map('intval', $this->schedule['days'] ?? [1, 2, 3, 4, 5]);
    }

    public function time(): string
    {
        return (string) ($this->schedule['time'] ?? '08:00');
    }
}
