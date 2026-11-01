<?php

namespace App\Models\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use Database\Factories\SuperAdmin\AlertEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One occurrence of an alert rule's condition. Stays `open` (or
 * `acknowledged`) while the condition holds and is `resolved` automatically
 * when it clears, or by hand.
 */
#[Fillable(['alert_rule_id', 'fingerprint', 'severity', 'status', 'title', 'context', 'first_fired_at', 'last_fired_at', 'last_notified_at', 'escalated_at', 'occurrences', 'acknowledged_by', 'acknowledged_at', 'resolved_at'])]
class AlertEvent extends Model
{
    /** @use HasFactory<AlertEventFactory> */
    use HasFactory, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'context' => 'array',
            'first_fired_at' => 'datetime',
            'last_fired_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'escalated_at' => 'datetime',
            'occurrences' => 'integer',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AlertRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    /**
     * @param  Builder<AlertEvent>  $query
     * @return Builder<AlertEvent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
