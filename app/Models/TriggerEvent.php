<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'trigger_id',
    'target_type',
    'target_id',
    'workflow_id',
    'workspace_id',
    'event_data',
    'provider',
    'provider_event',
    'dedup_key',
    'status',
    'processing_started_at',
    'processed_at',
    'attempts',
])]
class TriggerEvent extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TriggerEvent $event) {
            if ($event->workflow_id && ! $event->target_type) {
                $event->target_type = 'workflow';
                $event->target_id = $event->workflow_id;
            }

            if ($event->target_type === 'workflow' && ! $event->workflow_id) {
                $event->workflow_id = $event->target_id;
            }
        });
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }
}
