<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'target_type',
    'target_id',
    'workflow_id',
    'workspace_id',
    'credential_id',
    'trigger_type_id',
    'name',
    'type',
    'is_active',
    'is_paused',
    'webhook_uuid',
    'webhook_provider',
    'webhook_external_id',
    'webhook_secret',
    'webhook_registered_url',
    'webhook_status',
    'webhook_status_message',
    'polling_interval_seconds',
    'polling_last_check_at',
    'polling_next_check_at',
    'polling_last_seen_ids',
    'schedule_expression',
    'schedule_next_run_at',
    'schedule_timezone',
    'max_concurrency',
    'rate_limit_count',
    'rate_limit_window',
    'total_events',
    'total_executions',
    'settings',
    'initial_message',
    'last_fired_at',
])]
class Trigger extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_paused' => 'boolean',
            'polling_last_check_at' => 'datetime',
            'polling_next_check_at' => 'datetime',
            'polling_last_seen_ids' => 'array',
            'schedule_next_run_at' => 'datetime',
            'last_fired_at' => 'datetime',
            'polling_interval_seconds' => 'integer',
            'max_concurrency' => 'integer',
            'rate_limit_count' => 'integer',
            'rate_limit_window' => 'integer',
            'total_events' => 'integer',
            'total_executions' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * The automation this trigger fires — a Workflow or an Agent.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function isForAgent(): bool
    {
        return $this->target_type === 'agent';
    }

    /**
     * Backwards-compatible alias: reads/writes the workflow target through the
     * polymorphic columns so callers using `workflow_id` keep working.
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TriggerEvent::class)->orderByDesc('created_at');
    }

    public function isWebhook(): bool
    {
        return $this->type === 'webhook';
    }

    public function isPolling(): bool
    {
        return $this->type === 'polling';
    }

    public function isScheduled(): bool
    {
        return $this->type === 'scheduled';
    }

    /**
     * Whether this trigger is bound to an app-specific provider (github, slack, …)
     * rather than a generic webhook/polling/schedule.
     */
    public function isAppSpecific(): bool
    {
        return $this->webhook_provider !== null
            && $this->webhook_provider !== ''
            && $this->webhook_provider !== 'webhook';
    }

    /**
     * User-supplied config field values for an app-specific trigger, keyed by field_name.
     *
     * @return array<string, mixed>
     */
    public function getFieldValues(): array
    {
        return $this->settings['field_values'] ?? [];
    }

    public function getFieldValue(string $fieldName): mixed
    {
        return $this->getFieldValues()[$fieldName] ?? null;
    }
}
