<?php

namespace App\Http\Resources\Api\Internal\V1\Connectors;

use App\Models\Connectors\ConnectorCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately never includes `data`: decrypted secrets must never leave
 * the server once stored, no matter who's asking.
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
            'connector' => ConnectorResource::make($this->whenLoaded('connector')),
            'connector_id' => $this->connector_id,
            'scope' => $this->scope->value,
            'is_default' => $this->is_default,
            'name' => $this->name,
            'account_label' => $this->account_label,
            // Expired for the member: the token lapsed and can't be refreshed.
            'is_expired' => ! $this->isUsable(),
            'last_used_at' => $this->last_used_at,
            'last_tested_at' => $this->last_tested_at,
            'last_test_ok' => $this->last_test_ok,
            'last_test_message' => $this->last_test_message,
            'expires_at' => $this->expires_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
