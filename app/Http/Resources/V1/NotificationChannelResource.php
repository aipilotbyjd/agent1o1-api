<?php

namespace App\Http\Resources\V1;

use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationChannel
 */
class NotificationChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            // Config may contain webhook URLs / tokens — only expose non-sensitive hints.
            'config' => [
                'has_url' => isset($this->config['url']),
                'to' => $this->config['to'] ?? null,
            ],
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
