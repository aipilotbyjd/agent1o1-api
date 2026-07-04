<?php

namespace App\Http\Resources\V1;

use App\Models\CredentialType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CredentialType
 */
class CredentialTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'auth_type' => $this->auth_type,
            'icon' => $this->icon,
            'fields' => $this->fields,
            // Never expose token endpoints' client secrets; only public oauth metadata.
            'oauth' => $this->when($this->isOAuth(), fn () => [
                'scopes' => $this->oauth['scopes'] ?? [],
            ]),
            'sort_order' => $this->sort_order,
        ];
    }
}
