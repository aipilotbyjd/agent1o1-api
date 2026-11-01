<?php

namespace App\Services\SuperAdmin\Auth;

use App\Models\SuperAdmin\SuperAdmin;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Locks a single account after repeated failed sign-ins, whatever the number
 * of source addresses (the route rate limiter handles one noisy address).
 *
 * The count is never reset by time, only by a successful sign-in, and the
 * account locks on every multiple of `max_attempts`, with the lock growing
 * through `backoff` each time.
 */
class SuperAdminLoginThrottle
{
    public function assertNotLocked(SuperAdmin $admin): void
    {
        if (! $admin->isLocked()) {
            return;
        }

        throw new TooManyRequestsHttpException(
            max(1, $admin->locked_until->getTimestamp() - now()->getTimestamp()),
            'This account is temporarily locked. Try again later.',
        );
    }

    /**
     * Counts a failure; returns the lock length in seconds if it locked the account.
     */
    public function recordFailure(?SuperAdmin $admin): ?int
    {
        if ($admin === null) {
            return null;
        }

        $maxAttempts = max(1, (int) config('super_admin_auth.lockout.max_attempts'));

        $admin->increment('failed_login_count');

        if ($admin->failed_login_count % $maxAttempts !== 0) {
            return null;
        }

        $backoff = array_values(config('super_admin_auth.lockout.backoff'));
        $tier = intdiv($admin->failed_login_count, $maxAttempts) - 1;
        $minutes = $backoff[min($tier, count($backoff) - 1)];

        $admin->forceFill(['locked_until' => now()->addMinutes($minutes)])->save();

        return $minutes * 60;
    }

    public function clear(SuperAdmin $admin): void
    {
        if ($admin->failed_login_count === 0 && $admin->locked_until === null) {
            return;
        }

        $admin->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();
    }
}
