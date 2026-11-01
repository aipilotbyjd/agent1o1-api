<?php

namespace App\Services\Referrals;

/**
 * Referral fraud checks compare signups by network, but a raw IP is never
 * stored, only this keyed hash, which can be matched but not reversed.
 */
class IpHasher
{
    public static function hash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
