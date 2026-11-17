<?php

namespace App\Http\Resources\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Notifications\InboxNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A notification in the member's inbox. `category`, `severity` and `action`
 * come from the event's definition, so the app renders any event without
 * knowing about it, and rows stored before those columns existed still get
 * them.
 *
 * @mixin InboxNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->data) ? $this->data : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $workspaceId = $this->workspace_id ?? ($payload['workspace_id'] ?? null);
        $event = $this->resource instanceof InboxNotification
            ? $this->resource->event()
            : NotificationEvent::tryFrom((string) ($payload['type'] ?? ''));
        $actionPath = $event?->actionPath(is_string($workspaceId) ? $workspaceId : null, $data);
        $actionLabel = $event?->actionLabel($data);

        return [
            'id' => $this->id,
            'type' => $payload['type'] ?? null,
            'category' => $this->resource instanceof InboxNotification
                ? $this->resource->resolvedCategory()?->value
                : $event?->definition()->category->value,
            'severity' => $this->resource instanceof InboxNotification
                ? $this->resource->resolvedSeverity()->value
                : ($event?->definition()->severity->value ?? 'info'),
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
            'data' => $data,
            'workspace_id' => $workspaceId,
            'action' => $actionPath !== null ? ['label' => $actionLabel, 'url' => $actionPath] : null,
            'needs_action' => $event?->definition()->needsAction ?? false,
            'group_count' => (int) ($this->group_count ?? 1),
            'read_at' => $this->read_at,
            'archived_at' => $this->archived_at ?? null,
            'occurred_at' => $this->occurred_at ?? $this->created_at,
            'created_at' => $this->created_at,
        ];
    }
}
