<?php

namespace App\Notifications\Definitions;

/**
 * How repeats of one event fold together. Within `$windowMinutes`, a repeat
 * about the same thing (the same value of `$by` in the notification's data,
 * e.g. the same workflow) bumps the count on the member's unread notification
 * instead of adding another, and sends no further email or chat message.
 *
 * `$by` is null when every occurrence of the event in a workspace is "the same
 * thing" for grouping purposes.
 */
final readonly class NotificationGrouping
{
    public function __construct(
        public ?string $by = null,
        public int $windowMinutes = 60,
    ) {}
}
