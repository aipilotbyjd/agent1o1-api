<?php

namespace App\Enums\Notifications;

/**
 * Whose preferences decide an event's channels: a member's settings in the
 * workspace it happened in, or the person's account-wide settings.
 */
enum NotificationScope: string
{
    case Workspace = 'workspace';
    case Account = 'account';
}
