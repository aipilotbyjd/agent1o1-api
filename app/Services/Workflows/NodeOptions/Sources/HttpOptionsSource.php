<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\OptionsSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for sources that read a third-party API with the
 * member's connected account. Provider error bodies are logged, never
 * returned: the member sees a short, fixed message instead, so nothing a
 * provider says (or leaks) reaches the browser.
 */
abstract class HttpOptionsSource implements OptionsSource
{
    protected const int TIMEOUT_SECONDS = 15;

    /**
     * Shown in error messages, e.g. "Google Sheets".
     */
    abstract protected function appName(): string;

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withToken((string) $query->token)->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $params  null values are dropped
     * @return array<mixed>
     *
     * @throws ConnectorException
     */
    protected function getJson(NodeOptionsQuery $query, string $url, array $params = []): array
    {
        try {
            $response = $this->http($query)->get($url, array_filter($params, fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        $this->ensureSuccessful($response, $url);

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @throws ConnectorException
     */
    protected function ensureSuccessful(Response $response, string $url): void
    {
        if ($response->successful()) {
            return;
        }

        $this->logFailure(['url' => $url, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        throw new ConnectorException(match ($response->status()) {
            401 => "{$this->appName()} rejected the connected account. Reconnect it in Apps.",
            403 => "The connected {$this->appName()} account doesn't have access to this list.",
            404 => "{$this->appName()} couldn't find that item. Check the fields above.",
            429 => "{$this->appName()} is rate limiting requests. Try again in a moment.",
            default => "Couldn't load options from {$this->appName()} (HTTP {$response->status()}).",
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logFailure(array $context): void
    {
        Log::warning('Node options request failed.', ['app' => $this->appName(), ...$context]);
    }

    /**
     * A numeric page/offset cursor: anything else the client sends falls
     * back to the first page rather than reaching the provider.
     */
    protected function intCursor(NodeOptionsQuery $query, int $first = 1): int
    {
        $cursor = $query->cursor;

        return $cursor !== null && ctype_digit($cursor) ? max($first, (int) $cursor) : $first;
    }
}
