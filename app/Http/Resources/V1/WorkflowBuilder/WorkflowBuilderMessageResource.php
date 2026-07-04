<?php

namespace App\Http\Resources\V1\WorkflowBuilder;

use App\Models\WorkflowBuilderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderMessage
 */
class WorkflowBuilderMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'actions' => $this->actions,
            'processing_status' => $this->processing_status,
            'error_message' => $this->when($this->isFailed(), $this->error_message),
            'draft_version_id' => $this->draft_version_id,
            'created_at' => $this->created_at,
        ];
    }
}
