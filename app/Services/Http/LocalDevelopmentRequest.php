<?php

namespace App\Services\Http;

use Illuminate\Http\Request;

/**
 * Whether a request comes from a developer's own machine: the app runs in the
 * `local` environment and was reached by a loopback or development host
 * (`localhost`, an IP loopback, `*.test`, `*.localhost`, as Herd serves it).
 *
 * Safeguards that are relaxed for local development (the Stripe webhook
 * signature, the Horizon and Pulse dashboards) ask this instead of checking
 * `APP_ENV` alone. A server that is reached by a public name but was left on
 * `APP_ENV=local` then stays locked down instead of silently open.
 */
class LocalDevelopmentRequest
{
    /**
     * @var list<string>
     */
    private const DEVELOPMENT_SUFFIXES = ['.test', '.localhost'];

    /**
     * @var list<string>
     */
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

    public function matches(Request $request): bool
    {
        return app()->environment('local') && $this->isDevelopmentHost($request->getHost());
    }

    public function isDevelopmentHost(string $host): bool
    {
        $host = strtolower($host);

        if (in_array($host, self::LOOPBACK_HOSTS, strict: true)) {
            return true;
        }

        foreach (self::DEVELOPMENT_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
