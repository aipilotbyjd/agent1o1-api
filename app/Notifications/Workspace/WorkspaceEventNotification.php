<?php

namespace App\Notifications\Workspace;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

/**
 * Base class for notifications about something that happened in a workspace.
 * Concrete notifications (`MemberInvitedNotification`, etc.) supply only
 * title, body and data; channels, grouping, links and delivery come from
 * `AppNotification` and the event's definition.
 */
abstract class WorkspaceEventNotification extends AppNotification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly Workspace $workspace,
        NotificationEvent $event,
        string $title,
        ?string $body = null,
        array $data = [],
    ) {
        parent::__construct(
            event: $event,
            title: $title,
            body: $body,
            data: $data,
            workspaceId: (string) $workspace->getKey(),
        );
    }
}
