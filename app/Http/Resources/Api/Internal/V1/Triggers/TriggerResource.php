<?php

namespace App\Http\Resources\Api\Internal\V1\Triggers;

use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trigger
 */
class TriggerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'trigger_node_key' => $this->trigger_node_key,
            'type' => $this->type->value,
            'preset_id' => $this->preset_id,
            'config' => $this->config,
            'token' => $this->when($this->type->usesToken(), $this->token),
            'has_signing_secret' => filled($this->signing_secret),
            // The connected account a polling trigger reads with; null otherwise.
            'credential_id' => $this->credential_id,
            'is_active' => $this->is_active,
            'consecutive_failure_count' => $this->consecutive_failure_count,
            'last_run_at' => $this->last_run_at,
            'next_run_at' => $this->next_run_at,
            'next_poll_at' => $this->next_poll_at,
            // How events arrive and whether it is working: see TriggerStatus.
            'status' => app(TriggerStatus::class)->for($this->resource),
            'consecutive_skip_count' => $this->consecutive_skip_count,
            'max_failures' => $this->maxFailures(),
            // Only on the list endpoint, which counts them in one query.
            'stats_24h' => $this->when(isset($this->fired_24h_count), fn (): array => [
                'fired' => (int) $this->fired_24h_count,
                'failed' => (int) $this->failed_24h_count,
                'skipped' => (int) $this->skipped_24h_count,
                'rejected' => (int) $this->rejected_24h_count,
            ]),
            'target_name' => $this->whenLoaded('target', fn (): ?string => $this->target?->name),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
