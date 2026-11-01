<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Models;

use App\Models\Ai\ModelPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModelPrice
 */
class SuperAdminModelPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'model_id' => $this->model_id,
            'input_per_mtok' => $this->input_per_mtok,
            'output_per_mtok' => $this->output_per_mtok,
            'source' => $this->source->value,
            'checked_at' => $this->checked_at,
            'notes' => $this->notes,
            'updated_at' => $this->updated_at,
        ];
    }
}
