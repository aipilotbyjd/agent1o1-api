<?php

namespace App\Http\Middleware;

use App\Enums\Auth\ApiKeyAbility;
use App\Models\Auth\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    /** How stale `last_used_at` may get before a request rewrites it. */
    private const int LAST_USED_RESOLUTION_MINUTES = 5;

    /**
     * Handle an incoming request. Optionally guards a specific ability, e.g.
     * ->middleware('api-key:workflows:read')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        // The route group authenticates once; the ability-scoped groups nested
        // inside it reuse that key instead of querying and touching it again.
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            $plainTextKey = $request->bearerToken();

            abort_if($plainTextKey === null, 401, 'Missing API key.');

            $apiKey = ApiKey::query()->where('hashed_key', ApiKey::hash($plainTextKey))->first();

            abort_if($apiKey === null, 401, 'Invalid API key.');
            abort_if($apiKey->isExpired(), 401, 'This API key has expired.');

            // Only touched when the stored time is stale: "last used" doesn't
            // need a write on every request.
            if ($apiKey->last_used_at === null || $apiKey->last_used_at->lt(now()->subMinutes(self::LAST_USED_RESOLUTION_MINUTES))) {
                $apiKey->forceFill(['last_used_at' => now()])->save();
            }

            $request->attributes->set('api_key', $apiKey);
            $request->attributes->set('workspace', $apiKey->workspace);
        }

        if ($ability !== null) {
            abort_unless($apiKey->hasAbility(ApiKeyAbility::from($ability)), 403, 'This API key is missing the required ability.');
        }

        return $next($request);
    }
}
