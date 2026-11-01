<?php

namespace App\Services\SuperAdmin\System;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;

/**
 * The stable name a scheduled task is recorded under: the artisan command
 * (`triggers:run-due`) or, for a closure or job, its description.
 */
class ScheduledCommandName
{
    public static function for(Event $event): string
    {
        if ($event instanceof CallbackEvent) {
            return $event->getSummaryForDisplay();
        }

        $command = (string) $event->command;

        if (preg_match("/artisan['\"]?\\s+(.+)$/", $command, $matches) === 1) {
            return trim($matches[1], " '\"");
        }

        return $event->getSummaryForDisplay();
    }
}
