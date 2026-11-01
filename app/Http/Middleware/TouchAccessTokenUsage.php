<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a session's `last_used_at` and device columns roughly current so the
 * sessions list can show where and when each one was last seen.
 *
 * The staleness test is expressed in the UPDATE's own WHERE clause rather than
 * read first and compared in PHP: that keeps this to a single primary-key
 * statement per request that matches no rows (and so writes nothing) while the
 * stamp is younger than `TOUCH_INTERVAL_MINUTES`. Writing on every
 * authenticated request would add a real write to every API call for a field
 * nobody reads to the second.
 */
class TouchAccessTokenUsage
{
    /**
     * How stale a session's `last_used_at` may get before a request rewrites it.
     */
    private const TOUCH_INTERVAL_MINUTES = 5;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $tokenId = $this->currentTokenId($request);

        if ($tokenId === null) {
            return $response;
        }

        Passport::token()->newQuery()
            ->whereKey($tokenId)
            ->where(fn ($query) => $query
                ->whereNull('last_used_at')
                ->orWhere('last_used_at', '<=', now()->subMinutes(self::TOUCH_INTERVAL_MINUTES)))
            ->update([
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'last_used_at' => now(),
            ]);

        return $response;
    }

    /**
     * The guard hands back a `Laravel\Passport\AccessToken` (a value object over
     * the JWT claims), not the Eloquent row, so the id comes from the
     * `oauth_access_token_id` claim: reading `->id` instead would lazily fetch
     * the whole row just to learn its key. Cookie-authenticated requests carry a
     * `TransientToken`, which has no row to stamp.
     */
    private function currentTokenId(Request $request): ?string
    {
        // Checked before touching the guard on purpose: this middleware runs on
        // every API request, and asking for the user would build the Passport
        // token guard (and read the signing keys) even on public routes that
        // never authenticate anyone.
        if ($request->bearerToken() === null) {
            return null;
        }

        // Super Admin tokens are opaque strings of their own, not Passport
        // JWTs: asking the Passport guard to parse one would only fail (and
        // log) on every request.
        if ($request->routeIs('super-admin.*')) {
            return null;
        }

        $token = $request->user('api')?->token();

        if ($token === null || $token->transient()) {
            return null;
        }

        $tokenId = $token->oauth_access_token_id;

        return is_string($tokenId) && $tokenId !== '' ? $tokenId : null;
    }
}
