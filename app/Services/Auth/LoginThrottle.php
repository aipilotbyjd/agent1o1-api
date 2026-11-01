<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Per-account lockout for repeated failed sign-ins.
 *
 * Distinct from the `auth` rate limiter in `AppServiceProvider`, which is keyed
 * on IP and email and caps request *rate*. This counts failures against one
 * account across every source and stops answering them for a while: the lock
 * lengthens with each repeat so a patient attacker gains nothing by waiting the
 * first one out.
 *
 * Keyed on a hash of the address so no cache store ever holds a plaintext email,
 * and counted for addresses that don't resolve to an account too: revealing
 * "this one isn't locked, so it doesn't exist" would hand over an account
 * enumeration oracle.
 */
class LoginThrottle
{
    public function assertNotLocked(string $email): void
    {
        $lockedUntil = Cache::get($this->lockKey($email));

        if ($lockedUntil === null) {
            return;
        }

        $seconds = max(1, $lockedUntil - now()->getTimestamp());

        throw new TooManyRequestsHttpException(
            $seconds,
            'Too many failed sign-in attempts. Try again in '.$this->humanize($seconds).'.',
        );
    }

    /**
     * @return int|null Seconds the account was just locked for, or null if this
     *                  failure did not trip the threshold.
     */
    public function recordFailure(string $email): ?int
    {
        $attempts = (int) Cache::get($this->attemptKey($email), 0) + 1;

        Cache::put(
            $this->attemptKey($email),
            $attempts,
            now()->addMinutes((int) config('auth.lockout.attempt_window_minutes')),
        );

        if ($attempts < (int) config('auth.lockout.max_attempts')) {
            return null;
        }

        return $this->lock($email);
    }

    public function clear(string $email): void
    {
        Cache::forget($this->attemptKey($email));
        Cache::forget($this->lockKey($email));
    }

    /**
     * Locks the account for the next duration in the backoff ladder and counts
     * the lockout so the following one lasts longer.
     *
     * @return int Seconds locked.
     */
    private function lock(string $email): int
    {
        $escalationWindow = now()->addHours((int) config('auth.lockout.escalation_window_hours'));

        $priorLockouts = (int) Cache::get($this->lockoutCountKey($email), 0);
        Cache::put($this->lockoutCountKey($email), $priorLockouts + 1, $escalationWindow);

        /** @var array<int, int> $backoff */
        $backoff = config('auth.lockout.backoff');
        $minutes = $backoff[min($priorLockouts, count($backoff) - 1)];
        $seconds = $minutes * 60;

        Cache::put($this->lockKey($email), now()->getTimestamp() + $seconds, now()->addSeconds($seconds));
        Cache::forget($this->attemptKey($email));

        return $seconds;
    }

    private function humanize(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.str('second')->plural($seconds);
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.str('minute')->plural($minutes);
    }

    private function attemptKey(string $email): string
    {
        return 'auth-throttle:attempts:'.$this->hash($email);
    }

    private function lockKey(string $email): string
    {
        return 'auth-throttle:locked:'.$this->hash($email);
    }

    private function lockoutCountKey(string $email): string
    {
        return 'auth-throttle:lockouts:'.$this->hash($email);
    }

    private function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
