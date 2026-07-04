<?php

namespace App\Http\Resources\V1;

use App\Models\ExecutionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutionLog
 */
class ExecutionLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'execution_id' => $this->execution_id,
            'node_id' => $this->node_id,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'logged_at' => $this->logged_at,
        ];
    }
}
