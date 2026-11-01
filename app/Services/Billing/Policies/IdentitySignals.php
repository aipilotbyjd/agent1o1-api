<?php

namespace App\Services\Billing\Policies;

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\Credentials\OAuthConnection;
use App\Models\User;
use App\Services\Referrals\ReferralSettings;

/**
 * Hashed signals that tie several accounts to one person, for the free
 * allowance and trial checks. Hashed with the app key, so the claims tables
 * never hold an email address or IP in the clear.
 */
class IdentitySignals
{
    /**
     * Mailbox providers that ignore dots in the local part.
     *
     * @var list<string>
     */
    private const array DOT_INSENSITIVE_DOMAINS = ['gmail.com', 'googlemail.com'];

    public function __construct(private readonly ReferralSettings $referralSettings) {}

    /**
     * `J.Doe+promo@GoogleMail.com` and `jdoe@gmail.com` are one mailbox, so
     * they hash the same: lowercase, drop any `+tag`, and on Gmail drop dots
     * and fold googlemail.com into gmail.com.
     */
    public function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];

        if (in_array($domain, self::DOT_INSENSITIVE_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public function emailHash(User $user): ?string
    {
        return $user->email ? $this->hash('email:'.$this->normalizeEmail($user->email)) : null;
    }

    /**
     * The first linked social login (Google, GitHub), which survives an
     * email change.
     */
    public function oauthHash(User $user): ?string
    {
        $connection = OAuthConnection::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->first(['provider', 'provider_id']);

        return $connection !== null ? $this->hash('oauth:'.$connection->provider.':'.$connection->provider_id) : null;
    }

    /**
     * The network the account signed up from: the /24 of an IPv4 address or
     * the /64 of an IPv6 one, so one office or home counts once whatever
     * address the router hands out.
     */
    public function networkHash(User $user): ?string
    {
        $ip = AuthEventLog::query()
            ->where('user_id', $user->id)
            ->where('event', AuthEvent::Registered)
            ->orderBy('created_at')
            ->value('ip_address');

        $network = $this->networkOf($ip);

        return $network !== null ? $this->hash('net:'.$network) : null;
    }

    public function networkOf(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3)).'.0/24';
        }

        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        return bin2hex(substr($packed, 0, 8)).'::/64';
    }

    public function isDisposableEmail(User $user): bool
    {
        $domain = str_contains((string) $user->email, '@') ? explode('@', (string) $user->email, 2)[1] : null;

        return $domain !== null && $this->referralSettings->isBlockedDomain($domain);
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
