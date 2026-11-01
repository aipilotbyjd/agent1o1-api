<?php

namespace App\Services\Auth;

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use App\Notifications\Auth\SecurityAlertNotification;

/**
 * Writes the `auth_events` audit trail and, for the events
 * `AuthEvent::notifiesUser()` marks, emails the account owner.
 *
 * Recording and notifying live together on purpose: every call site that has
 * enough context to log an event has exactly the context the alert needs, and
 * keeping them apart reliably produced logged-but-unannounced events.
 *
 * The request is resolved per call rather than injected: the router caches a
 * controller instance on its Route, so anything this class held from its
 * constructor would describe whichever request happened to build it first.
 */
class AuthEventRecorder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(AuthEvent $event, ?User $user = null, ?string $email = null, array $context = []): AuthEventLog
    {
        $ipAddress = request()->ip();
        $userAgent = $this->userAgent();

        $log = AuthEventLog::query()->create([
            'user_id' => $user?->getKey(),
            'email' => $email ?? $user?->email,
            'event' => $event,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'context' => $context === [] ? null : $context,
        ]);

        if ($user !== null && $event->notifiesUser()) {
            $user->notify(new SecurityAlertNotification($event, $ipAddress, $userAgent));
        }

        return $log;
    }

    /**
     * Sign-ins from an address/agent pair this account has never used before are
     * worth telling the owner about; every subsequent sign-in from it is not.
     */
    public function recordLogin(User $user): void
    {
        $isKnownDevice = AuthEventLog::query()
            ->where('user_id', $user->getKey())
            ->where('event', AuthEvent::LoggedIn->value)
            ->where('ip_address', request()->ip())
            ->where('user_agent', $this->userAgent())
            ->exists();

        $this->record(AuthEvent::LoggedIn, $user, context: ['new_device' => ! $isKnownDevice]);

        if (! $isKnownDevice) {
            $user->notify(new SecurityAlertNotification(AuthEvent::LoggedIn, request()->ip(), $this->userAgent()));
        }
    }

    /**
     * Truncated to the column's practical width: a user agent is context for a
     * human reading the log, not something we parse.
     */
    private function userAgent(): ?string
    {
        $userAgent = request()->userAgent();

        return $userAgent === null ? null : mb_substr($userAgent, 0, 500);
    }
}
