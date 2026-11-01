<?php

namespace App\Services\Workflows\Pages;

use App\Models\Workflows\WorkflowPage;
use Illuminate\Support\Facades\Hash;

/**
 * What a visitor holds after entering a password-protected page's password:
 * a short-lived token, sent back as `X-Page-Access`. It's an HMAC over the
 * page, its expiry and the stored password hash, so changing the password
 * or rotating the link logs everyone out without keeping any session state.
 */
class PageAccessTokens
{
    public const string HEADER = 'X-Page-Access';

    private const int TTL_SECONDS = 12 * 3600;

    public function attempt(WorkflowPage $page, string $password): ?string
    {
        if ($page->password === null || ! Hash::check($password, $page->password)) {
            return null;
        }

        $expires = now()->addSeconds(self::TTL_SECONDS)->getTimestamp();

        return $expires.'.'.$this->signature($page, $expires);
    }

    public function allows(WorkflowPage $page, ?string $token): bool
    {
        if ($page->password === null || $token === null || ! str_contains($token, '.')) {
            return false;
        }

        [$expires, $signature] = explode('.', $token, 2);

        return ctype_digit($expires)
            && (int) $expires > now()->getTimestamp()
            && hash_equals($this->signature($page, (int) $expires), $signature);
    }

    private function signature(WorkflowPage $page, int $expires): string
    {
        return hash_hmac('sha256', "{$page->id}|{$page->public_token}|{$expires}|{$page->password}", (string) config('app.key'));
    }
}
