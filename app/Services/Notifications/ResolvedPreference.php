<?php

namespace App\Services\Notifications;

/**
 * Which channels one member gets one event on, after every layer of
 * preference (`PreferenceResolver`) has been applied.
 */
final readonly class ResolvedPreference
{
    public function __construct(
        public bool $inApp,
        public bool $email,
        public bool $emailDigest = false,
    ) {}

    /**
     * Whether email is held back for the daily digest rather than sent now.
     */
    public function sendsDigest(): bool
    {
        return $this->email && $this->emailDigest;
    }
}
