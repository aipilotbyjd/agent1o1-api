<?php

namespace App\Http\Resources\V1;

use App\Models\WorkflowShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowShare
 */
class WorkflowShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'token' => $this->token,
            'url' => url("/api/v1/shared/{$this->token}"),
            'allow_clone' => $this->allow_clone,
            'expires_at' => $this->expires_at,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at,
        ];
    }
}
