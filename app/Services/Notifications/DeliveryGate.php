<?php

namespace App\Services\Notifications;

use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Stops the same message reaching the same place twice. Applied to outbound
 * channels (email, digest, chat and webhooks); the inbox does its own
 * de-duplication on the row itself (`InboxChannel`).
 *
 * - Idempotent notifications (`AppNotification::idempotencyKey()`) go out
 *   once per destination however often their trigger fires, e.g. Stripe
 *   retrying a webhook.
 * - Grouped notifications (`NotificationDefinition::$grouping`) go out once
 *   per destination per grouping window; later repeats only bump the inbox
 *   count.
 *
 * The idempotency check runs first so a duplicate never uses up the group's
 * slot.
 */
class DeliveryGate
{
    private const string PREFIX = 'notifications:gate:';

    public function allows(AppNotification $notification, string $destination): bool
    {
        $idempotencyKey = $notification->idempotencyKey();

        if ($idempotencyKey !== null && ! Cache::add(
            self::PREFIX."once:{$idempotencyKey}:{$destination}",
            true,
            now()->addHours((int) config('notifications.idempotency_hours')),
        )) {
            return false;
        }

        $groupKey = $notification->groupKey();
        $grouping = $notification->type->definition()->grouping;

        if ($groupKey === null || $grouping === null) {
            return true;
        }

        return Cache::add(
            self::PREFIX."group:{$groupKey}:{$destination}",
            true,
            now()->addMinutes($grouping->windowMinutes),
        );
    }
}
