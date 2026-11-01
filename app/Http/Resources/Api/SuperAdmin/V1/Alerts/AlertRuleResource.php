<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Alerts;

use App\Models\SuperAdmin\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertRule
 */
class AlertRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->config,
            'severity' => $this->severity->value,
            'cooldown_minutes' => $this->cooldown_minutes,
            'enabled' => $this->enabled,
            'snoozed_until' => $this->snoozed_until,
            'is_snoozed' => $this->isSnoozed(),
            'open_events' => $this->when(isset($this->open_events_count), fn (): int => (int) $this->open_events_count),
            'updated_at' => $this->updated_at,
        ];
    }
}
