<?php

namespace App\Services\Ai;

/**
 * Decides whether a URL may be saved as an operator endpoint, so the panel
 * cannot be made to call an internal service: the server makes real requests
 * to whatever address is stored (the connection test, every model call).
 *
 * HTTPS only (plain HTTP just locally), and the host must resolve to public
 * addresses only: loopback, private ranges, link-local (the cloud metadata
 * address among them) and reserved ranges are refused. A host that is meant
 * to be private (a vLLM box on the internal network) has to be named in
 * `super_admin_security.endpoints.private_hosts`, which only someone with
 * access to the deployment's environment can change.
 */
class EndpointUrlGuard
{
    /**
     * What is wrong with `$url`, or null when it may be used.
     */
    public function problem(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Enter a full URL, such as https://llm.example.com/v1.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Put the key in the API key field, not in the URL.';
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $allowlisted = in_array($host, $this->privateHosts(), true);
        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ! ($scheme === 'http' && ($allowlisted || app()->environment(['local', 'testing'])))) {
            return 'The URL must use https.';
        }

        if ($allowlisted) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve($host) ?: []);

        if ($addresses === []) {
            return "The host [{$host}] does not resolve.";
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return "The host [{$host}] points at a private or reserved address. If that is intended, add it to SUPER_ADMIN_ENDPOINT_PRIVATE_HOSTS.";
            }
        }

        return null;
    }

    /**
     * @return array<int, string>|false
     */
    protected function resolve(string $host): array|false
    {
        return gethostbynamel($host);
    }

    /**
     * @return array<int, string>
     */
    private function privateHosts(): array
    {
        return array_map('strtolower', (array) config('super_admin_security.endpoints.private_hosts', []));
    }
}
