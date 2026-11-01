<?php

namespace App\Http\Resources\Api\Internal\V1\Connectors;

use App\Models\Connectors\Connector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'icon' => $this->icon,
            'color' => $this->color,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'category_icon' => $this->category->icon(),
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'auth_type' => $this->auth_type,
            'is_oauth' => $this->isOAuth(),
            'is_configured' => $this->isConfigured(),
            'fields' => $this->fields,
            'is_active' => $this->is_active,
        ];
    }
}
