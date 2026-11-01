<?php

namespace App\Services\Http;

use App\Exceptions\Http\BlockedUrlException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one way to fetch a tenant-supplied URL. Wraps Laravel's HTTP client so
 * every request is checked by {@see SsrfGuard} and cannot be redirected or
 * re-resolved into an internal address:
 *
 *  - the URL is validated before each request, and each `Location` hop is
 *    followed manually and validated before the client connects to it;
 *  - the connection is pinned (`CURLOPT_RESOLVE`) to the addresses that were
 *    validated, so a DNS answer that flips between the check and the connect
 *    (DNS rebinding) cannot reach a blocked address;
 *  - headers are dropped when a redirect leaves the original origin, so a
 *    tenant's API key is never replayed to a third party;
 *  - the response body is capped, so a hostile server cannot exhaust memory.
 */
class GuardedHttp
{
    public const int MAX_REDIRECTS = 5;

    public const int MAX_RESPONSE_BYTES = 10 * 1024 * 1024;

    /**
     * Headers that are safe to keep when a redirect crosses origins.
     *
     * @var list<string>
     */
    private const CROSS_ORIGIN_HEADERS = ['accept', 'accept-language', 'content-type', 'user-agent'];

    public function __construct(private readonly SsrfGuard $guard) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $json  Sent as the JSON body; `null` sends no body.
     * @param  array<string, mixed>  $query  Merged into the first request only: a redirect target already carries its own query.
     */
    public function send(
        string $method,
        string $url,
        array $headers = [],
        ?array $json = null,
        array $query = [],
        int $timeoutSeconds = 30,
        int $retries = 0,
    ): Response {
        $method = strtoupper($method);

        for ($hop = 0; ; $hop++) {
            $response = $this->sendOnce($method, $url, $headers, $json, $hop === 0 ? $query : [], $timeoutSeconds, $retries);

            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $response;
            }

            if ($hop >= self::MAX_REDIRECTS) {
                throw BlockedUrlException::forUrl($url, 'too many redirects.');
            }

            $next = $this->resolveRedirectUrl($url, $location);

            if (! $this->isSameOrigin($url, $next)) {
                $headers = array_filter(
                    $headers,
                    fn (string $name): bool => in_array(strtolower($name), self::CROSS_ORIGIN_HEADERS, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            // 303 always, and 301/302 for anything but GET/HEAD, become a body-less GET: what browsers do.
            if ($response->status() === 303 || (in_array($response->status(), [301, 302], true) && ! in_array($method, ['GET', 'HEAD'], true))) {
                $method = $method === 'HEAD' ? 'HEAD' : 'GET';
                $json = null;
            }

            $url = $next;
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>  $query
     */
    private function sendOnce(string $method, string $url, array $headers, ?array $json, array $query, int $timeoutSeconds, int $retries): Response
    {
        $target = $this->guard->resolve($url);

        $request = Http::withHeaders($headers)
            ->timeout($timeoutSeconds)
            ->connectTimeout(min($timeoutSeconds, 10))
            ->withOptions($this->options($target));

        if ($query !== []) {
            $request = $request->withQueryParameters($query);
        }

        if ($retries > 0) {
            $request = $request->retry($retries, 250, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || in_array($exception->response->status(), [408, 429], true))), throw: false);
        }

        return $request->send($method, $url, $json === null ? [] : ['json' => $json]);
    }

    /**
     * @param  array{host: string, port: int, ips: list<string>, pinnable: bool}  $target
     * @return array<string, mixed>
     */
    private function options(array $target): array
    {
        $options = [
            'allow_redirects' => false,
            'on_headers' => function ($response): void {
                if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
                    throw BlockedUrlException::forUrl('response', 'the response body is too large.');
                }
            },
            'progress' => function (int $downloadTotal, int $downloaded): void {
                if ($downloaded > self::MAX_RESPONSE_BYTES) {
                    throw BlockedUrlException::forUrl('response', 'the response body is too large.');
                }
            },
        ];

        if ($target['pinnable'] && defined('CURLOPT_RESOLVE')) {
            $addresses = array_map(
                fn (string $ip): string => str_contains($ip, ':') ? "[{$ip}]" : $ip,
                $target['ips'],
            );

            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".implode(',', $addresses)],
            ];
        }

        return $options;
    }

    private function resolveRedirectUrl(string $requestUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $base = parse_url($requestUrl);
        $origin = sprintf('%s://%s%s', $base['scheme'], $base['host'], isset($base['port']) ? ':'.$base['port'] : '');

        // Scheme-relative (`//host/path`): a different host, which the guard checks like any other.
        if (str_starts_with($location, '//')) {
            return $base['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = substr($base['path'] ?? '/', 0, (int) strrpos($base['path'] ?? '/', '/') + 1);

        return $origin.$directory.$location;
    }

    private function isSameOrigin(string $a, string $b): bool
    {
        return $this->origin($a) === $this->origin($b);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return sprintf(
            '%s://%s:%d',
            $scheme,
            strtolower((string) ($parts['host'] ?? '')),
            $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
        );
    }
}
