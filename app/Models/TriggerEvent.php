<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Backwards-compatible alias over the polymorphic target columns.
     */
    protected function workflowId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->target_type === 'workflow' ? $this->target_id : null,
            set: function ($value) {
                if ($value === null) {
                    return [];
                }

                return ['target_type' => 'workflow', 'target_id' => $value];
            },
        );
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'target_id');
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
