<?php

namespace App\Http\Resources\V1;

use App\Models\LogStreamingConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LogStreamingConfig
 */
class LogStreamingConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'destination' => $this->destination,
            'endpoint' => $this->endpoint,
            'is_active' => $this->is_active,
            'last_delivered_at' => $this->last_delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
