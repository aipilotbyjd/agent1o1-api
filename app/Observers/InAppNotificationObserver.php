<?php

namespace App\Observers;

use App\Events\InAppNotificationCreated;
use App\Models\InAppNotification;

class InAppNotificationObserver
{
    public function created(InAppNotification $notification): void
    {
        if ($notification->workspace_id) {
            InAppNotificationCreated::dispatch($notification);
        }
    }
}
