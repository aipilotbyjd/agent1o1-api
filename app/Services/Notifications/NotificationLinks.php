<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Turns the in-app paths notifications point at into links that work from
 * an email or a chat message, and builds the signed links that change a
 * member's settings without signing in.
 */
final class NotificationLinks
{
    /**
     * An absolute link for an in-app path, or the address itself when it is
     * already an https link. Paths with a `:workspace` placeholder (written by
     * a super admin, who can't know anyone's workspace) have no absolute form.
     */
    public static function absolute(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^https://[^\s/?#]+#i', $path) === 1) {
            return $path;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, ':workspace')) {
            return null;
        }

        return rtrim((string) config('app.frontend_url'), '/').$path;
    }

    public static function settings(?string $workspaceId): ?string
    {
        return $workspaceId === null ? null : self::absolute('/'.rawurlencode($workspaceId).'/settings/notifications');
    }

    /**
     * RFC 8058 one-click unsubscribe from one event's email. No expiry: an
     * old email must still be able to switch its own email off.
     */
    public static function oneClickUnsubscribe(User $user, NotificationEvent $event, ?string $workspaceId): string
    {
        return URL::signedRoute('notifications.unsubscribe.one-click', array_filter([
            'user' => $user->getKey(),
            'event' => $event->value,
            'workspace' => $workspaceId,
        ], fn (mixed $value): bool => $value !== null));
    }
}
