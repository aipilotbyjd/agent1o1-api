<?php

namespace App\Http\Resources\V1;

use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trigger
 */
class TriggerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'workflow_id' => $this->workflow_id,
            'trigger_type_id' => $this->trigger_type_id,
            'name' => $this->name,
            'type' => $this->type,
            'initial_message' => $this->initial_message,
            'last_fired_at' => $this->last_fired_at,
            'webhook_provider' => $this->webhook_provider,
            'is_active' => $this->is_active,
            'is_paused' => $this->is_paused,
            'webhook_url' => $this->when($this->isWebhook() && $this->webhook_uuid, fn () => url("/api/v1/webhooks/{$this->webhook_uuid}")),
            'webhook_status' => $this->webhook_status,
            'polling_interval_seconds' => $this->polling_interval_seconds,
            'polling_last_check_at' => $this->polling_last_check_at,
            'schedule_expression' => $this->schedule_expression,
            'schedule_timezone' => $this->schedule_timezone,
            'schedule_next_run_at' => $this->schedule_next_run_at,
            'total_events' => $this->total_events,
            'total_executions' => $this->total_executions,
            'settings' => $this->settings,
            'created_at' => $this->created_at,
        ];
    }
}
