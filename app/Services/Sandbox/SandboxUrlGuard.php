<?php

namespace App\Services\Sandbox;

use App\Services\Ai\EndpointUrlGuard;

/**
 * Decides whether a URL may be saved as a sandbox provider. On top of the
 * checks for any operator endpoint (https, public addresses only, see
 * `EndpointUrlGuard`) the provider must be a different machine: this app's
 * own host is refused, so a "runner" cannot be pointed back at the server
 * that holds the platform's secrets.
 */
class SandboxUrlGuard
{
    public function __construct(private readonly EndpointUrlGuard $endpoints) {}

    /**
     * What is wrong with `$url`, or null when it may be used.
     */
    public function problem(string $url): ?string
    {
        $problem = $this->endpoints->problem($url);

        if ($problem !== null) {
            return $problem;
        }

        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if ($host === '' || in_array($host, $this->ownHosts(), true)) {
            return 'A sandbox must run on a separate machine, not on this server.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function ownHosts(): array
    {
        $hosts = [
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.frontend_url'), PHP_URL_HOST),
            config('super_admin.host'),
            gethostname() ?: null,
            'localhost',
        ];

        return array_values(array_unique(array_map(
            fn (mixed $host): string => strtolower((string) $host),
            array_filter($hosts, fn (mixed $host): bool => is_string($host) && $host !== ''),
        )));
    }
}
