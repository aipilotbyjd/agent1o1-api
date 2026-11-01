<?php

namespace App\Enums\Broadcasts;

/**
 * Why an email is sent decides whether a member's opt-out applies. Marketing
 * mail (launches, offers, tips) always respects it and always carries an
 * unsubscribe link; service mail (an incident, a policy change, a deprecation)
 * is something every member must be able to receive.
 */
enum BroadcastCategory: string
{
    case Marketing = 'marketing';
    case Service = 'service';

    public function respectsOptOut(): bool
    {
        return $this === self::Marketing;
    }
}
