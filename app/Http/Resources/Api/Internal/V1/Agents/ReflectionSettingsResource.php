<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\ReflectionSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReflectionSettings
 */
class ReflectionSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'is_enabled' => $this->is_enabled,
            'apply_behavior' => $this->apply_behavior->value,
            'schedule_cron' => $this->schedule_cron,
            'min_chats_threshold' => $this->min_chats_threshold,
            'extra_instructions' => $this->extra_instructions,
            'notify_on_skip' => $this->notify_on_skip,
            'last_run_at' => $this->last_run_at,
            'next_run_at' => $this->next_run_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
