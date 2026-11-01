<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Models\Runs\Run;
use Database\Factories\Triggers\TriggerEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that arrived for a trigger: a webhook delivery, a schedule slot,
 * a polled item, or a "Run now".
 *
 * Written before any work happens, so every delivery leaves a record of what
 * became of it (TriggerEventStatus). `delivery_id` is unique per trigger and
 * is what stops a redelivered event from running twice.
 */
#[Fillable([
    'trigger_id', 'source', 'status', 'run_id', 'payload', 'payload_snippet',
    'headers', 'error', 'delivery_id', 'attempts', 'duplicate_count', 'processed_at',
])]
class TriggerEvent extends Model
{
    /** @use HasFactory<TriggerEventFactory> */
    use HasFactory, HasUuids;

    /**
     * Defaults for a new event: queued, untouched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
        'duplicate_count' => 0,
    ];

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TriggerType::class,
            'status' => TriggerEventStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'attempts' => 'integer',
            'duplicate_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The trigger it arrived for.
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * The run it started: a workflow run or an agent turn, depending on the
     * trigger's target. Null until the event is fired.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Claim a queued event for processing. Returns false if it isn't queued
     * or another worker claimed it first.
     *
     * The conditional update is the lock: when two workers race, exactly one
     * of them changes the row. A `running` event is never claimable, or two
     * workers could start the same event; a stranded one is made claimable
     * again by requeue().
     */
    public function claim(): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        $affected = self::query()
            ->whereKey($this->id)
            ->where('status', TriggerEventStatus::Queued->value)
            ->update(['status' => TriggerEventStatus::Running->value]);

        if ($affected === 1) {
            $this->status = TriggerEventStatus::Running;

            return true;
        }

        return false;
    }

    /**
     * Put an event back to `queued` so it can be claimed again: after a
     * failed attempt, or when the retry-stuck command finds it stranded.
     */
    public function requeue(): void
    {
        $this->update(['status' => TriggerEventStatus::Queued]);
    }

    /**
     * Record that the event started a run.
     */
    public function markFired(string $runId): void
    {
        $this->update([
            'status' => TriggerEventStatus::Fired,
            'run_id' => $runId,
            'processed_at' => now(),
        ]);
    }

    /**
     * Record a final outcome other than fired, with the reason if any.
     */
    public function finish(TriggerEventStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
