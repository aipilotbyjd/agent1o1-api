<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Connectors\ConnectorCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes `data`: the same rule as the internal resource: a stored
 * secret does not come back out, whoever is asking.
 *
 * @mixin ConnectorCredential
 */
class ConnectorCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'connector_id' => $this->connector_id,
            'connector' => ConnectorResource::make($this->whenLoaded('connector')),
            'name' => $this->name,
            // Expired for the member: the token lapsed and can't be refreshed.
            'is_expired' => ! $this->isUsable(),
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
