<?php

namespace App\Enums\Notifications;

/**
 * Where one delivery of a notification to an email address or a chat/webhook
 * channel stands (`notification_deliveries.status`).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
