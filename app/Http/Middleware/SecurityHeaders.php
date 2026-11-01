<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser-hardening headers on every response.
 *
 * A header a controller already set is left alone: artifact previews send
 * their own `Content-Security-Policy: sandbox`, which must not be replaced
 * (and which is why they are also not given frame protection here).
 *
 * The API only ever returns JSON or file bytes, so its CSP forbids loading
 * anything. Web routes (Horizon, Pulse) use inline scripts and styles, so
 * they get framing protection but no CSP.
 */
class SecurityHeaders
{
    private const int HSTS_MAX_AGE_SECONDS = 31_536_000;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if (! $response->headers->has('Content-Security-Policy')) {
            $headers += $request->is('api/*')
                ? ['Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'", 'X-Frame-Options' => 'DENY']
                : ['X-Frame-Options' => 'SAMEORIGIN'];
        }

        if ($this->shouldSendHsts()) {
            $headers['Strict-Transport-Security'] = 'max-age='.self::HSTS_MAX_AGE_SECONDS.'; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Decided from the configured URL rather than the request: behind a
     * TLS-terminating proxy the request itself reports plain HTTP.
     */
    private function shouldSendHsts(): bool
    {
        return app()->isProduction() && str_starts_with((string) config('app.url'), 'https://');
    }
}
