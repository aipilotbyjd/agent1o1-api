<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Connectors\Connector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The connector catalog as an integrator sees it: enough to know what a
 * credential for this connector must contain (`fields`).
 *
 * @mixin Connector
 */
class ConnectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'auth_type' => $this->auth_type,
            'is_oauth' => $this->isOAuth(),
            'fields' => $this->fields,
        ];
    }
}
