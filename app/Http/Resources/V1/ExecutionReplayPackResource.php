<?php

namespace App\Http\Resources\V1;

use App\Models\ExecutionReplayPack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutionReplayPack
 */
class ExecutionReplayPackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'execution_id' => $this->execution_id,
            'label' => $this->label,
            'version_snapshot' => $this->version_snapshot,
            'trigger_data' => $this->trigger_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
