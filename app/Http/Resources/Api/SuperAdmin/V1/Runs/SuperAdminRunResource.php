<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Runs;

use App\Models\Runs\Run;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A run's metadata for troubleshooting. Deliberately omits `input` and
 * `output`: they hold a customer's data, and a super admin debugging a
 * failure needs the status and error, not the payload.
 *
 * @mixin Run
 */
class SuperAdminRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'workspace' => $this->whenLoaded('workspace', fn () => $this->workspace === null ? null : [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
            ]),
            'workflow' => $this->whenLoaded('workflow', fn () => $this->workflow === null ? null : [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
            ]),
            'status' => $this->status->value,
            'trigger_type' => $this->trigger_type,
            'error' => $this->error,
            'parent_run_id' => $this->parent_run_id,
            'retried_from_run_id' => $this->retried_from_run_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
