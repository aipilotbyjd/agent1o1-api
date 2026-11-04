<?php

namespace App\Ai\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Retries a request to an AI provider when the connection could not be made
 * or dropped before any response came back: a flaky gateway then costs a
 * short pause instead of a failed turn or subagent.
 *
 * Only that case is retried. Nothing has been received at that point, so
 * there is no partial reply to duplicate, and anything the provider did
 * answer (an error status, rate limit or overload) is left to Laravel AI's own
 * handling and failover. Requests to anything but an AI provider are never
 * retried, so a workflow's own HTTP calls keep their one-shot behaviour.
 */
class RetriesProviderConnections
{
    public const int MAX_RETRIES = 2;

    /**
     * Provider hosts that are not configured with a `url`: the SDK knows
     * these itself.
     *
     * @var list<string>
     */
    private const array KNOWN_HOSTS = [
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com',
        'api.x.ai',
        'api.groq.com',
        'api.mistral.ai',
        'api.deepseek.com',
        'openrouter.ai',
    ];

    public static function middleware(): callable
    {
        return Middleware::retry(
            fn (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?Throwable $exception = null): bool => self::shouldRetry($retries, $request, $exception),
            fn (int $retries): int => self::delayMilliseconds($retries),
        );
    }

    public static function shouldRetry(int $retries, RequestInterface $request, ?Throwable $exception): bool
    {
        return $retries < self::MAX_RETRIES
            && $exception instanceof ConnectException
            && self::isProviderHost($request->getUri()->getHost());
    }

    /**
     * Backs off 0.5s, then 1.5s.
     */
    public static function delayMilliseconds(int $retries): int
    {
        return 500 * (3 ** max(0, $retries - 1));
    }

    private static function isProviderHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $configured = collect((array) config('ai.providers'))
            ->pluck('url')
            ->filter()
            ->map(fn (mixed $url): ?string => parse_url((string) $url, PHP_URL_HOST) ?: null)
            ->filter();

        return $configured->merge(self::KNOWN_HOSTS)->contains(strtolower($host));
    }
}
