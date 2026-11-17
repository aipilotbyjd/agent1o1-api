<?php

namespace App\Enums\Broadcasts;

/**
 * Where a campaign reaches people: their inbox, the notification bell in the
 * app, or both. Email needs a verified address; the bell only needs an account
 * that isn't suspended, so the two reach slightly different audiences.
 */
enum BroadcastChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';
    case EmailAndInApp = 'email_and_in_app';

    public function includesEmail(): bool
    {
        return $this !== self::InApp;
    }

    public function includesInApp(): bool
    {
        return $this !== self::Email;
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::InApp => 'In-app notification',
            self::EmailAndInApp => 'Email and in-app notification',
        };
    }
}
