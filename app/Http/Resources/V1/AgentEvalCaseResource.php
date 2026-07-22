<?php

namespace App\Http\Resources\V1;

use App\Models\AgentEvalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalCase
 */
class AgentEvalCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suite_id,
            'name' => $this->name,
            'input' => $this->input,
            'assertions' => $this->assertions,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
