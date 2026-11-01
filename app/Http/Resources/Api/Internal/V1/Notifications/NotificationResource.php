<?php

namespace App\Http\Resources\Api\Internal\V1\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? null,
            'title' => $this->data['title'] ?? null,
            'body' => $this->data['body'] ?? null,
            'data' => $this->data['data'] ?? [],
            'workspace_id' => $this->data['workspace_id'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
