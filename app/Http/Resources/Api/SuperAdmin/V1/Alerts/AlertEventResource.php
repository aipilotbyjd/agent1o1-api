<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Alerts;

use App\Models\SuperAdmin\AlertEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertEvent
 */
class AlertEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule' => $this->whenLoaded('rule', fn (): array => ['id' => $this->rule->id, 'key' => $this->rule->key, 'name' => $this->rule->name]),
            'fingerprint' => $this->fingerprint,
            'severity' => $this->severity->value,
            'status' => $this->status,
            'title' => $this->title,
            'context' => $this->context,
            'occurrences' => $this->occurrences,
            'first_fired_at' => $this->first_fired_at,
            'last_fired_at' => $this->last_fired_at,
            'last_notified_at' => $this->last_notified_at,
            'escalated_at' => $this->escalated_at,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
