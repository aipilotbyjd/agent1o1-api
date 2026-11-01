<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Runs\Run;
use App\Nodes\Support\FilePayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One way to call a JSON API that authenticates with a key: send the request
 * with the step's account, and turn the failures every such API has (a
 * refused key, no credit left, a rate limit, a server error) into a sentence a
 * person can act on, naming the service.
 *
 * The user of this trait says how the key is sent (`authenticate()`) and what
 * the service is called (`serviceName()`); the endpoint is always the
 * service's own, never a value from the workflow.
 */
trait CallsApiKeyService
{
    use ResolvesConnectorCredential;

    abstract protected function serviceName(): string;

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, string>, 1: array<string, mixed>} headers and query, with the key added
     */
    abstract protected function authenticate(string $key, array $headers, array $query): array;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $json  the JSON body; null sends none
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function call(Run $run, array $config, string $method, string $url, ?array $json = null, array $query = [], array $headers = []): array
    {
        [$headers, $query] = $this->authenticate($this->resolveAccessToken($run, $config), $headers, array_filter($query, fn (mixed $value): bool => $value !== null && $value !== ''));

        try {
            $request = Http::withHeaders($headers)->acceptJson()->timeout(30)->retry(2, 400, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false);
            $response = $json === null
                ? $request->send(strtoupper($method), $url, ['query' => $query])
                : $request->send(strtoupper($method), $url, ['query' => $query, 'json' => $json]);
        } catch (ConnectionException) {
            throw new RuntimeException("{$this->serviceName()} could not be reached. Try again in a moment.");
        }

        $this->assertSuccessful($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Download a file from the service (a signed document, an export) as raw
     * bytes, within the size limit a step may hold.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, string>  $headers
     * @return array{content: string, mime_type: string}
     *
     * @throws RuntimeException
     */
    protected function download(Run $run, array $config, string $url, array $query = [], array $headers = []): array
    {
        [$headers, $query] = $this->authenticate($this->resolveAccessToken($run, $config), $headers, array_filter($query, fn (mixed $value): bool => $value !== null && $value !== ''));

        try {
            $response = Http::withHeaders($headers)->timeout(60)->get($url, $query);
        } catch (ConnectionException) {
            throw new RuntimeException("{$this->serviceName()} could not be reached. Try again in a moment.");
        }

        $this->assertSuccessful($response);

        $body = $response->body();

        if (strlen($body) > FilePayload::MAX_BYTES) {
            throw new RuntimeException('The document is too large to bring into a workflow ('.round(strlen($body) / 1048576, 1).' MB; the limit is '.(FilePayload::MAX_BYTES / 1048576).' MB).');
        }

        return ['content' => $body, 'mime_type' => trim(explode(';', (string) $response->header('Content-Type'))[0]) ?: 'application/pdf'];
    }

    /**
     * @throws RuntimeException
     */
    protected function assertSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $service = $this->serviceName();
        $detail = $this->errorDetail($response);

        throw new RuntimeException(match (true) {
            in_array($response->status(), [401, 403], true) => "{$service} refused the API key{$detail}. Reconnect {$service} with a valid key that has access to this feature.",
            $response->status() === 402 => "{$service} says the account has no credit left or the plan does not include this{$detail}.",
            $response->status() === 429 => "{$service} is limiting requests{$detail}. Slow the workflow down or try again shortly.",
            $response->status() === 422 || $response->status() === 400 => "{$service} rejected the request{$detail}.",
            $response->status() === 404 => "{$service} found nothing for that request{$detail}.",
            default => "{$service} returned an error (HTTP {$response->status()}){$detail}.",
        });
    }

    private function errorDetail(Response $response): string
    {
        $body = $response->json();

        $message = is_array($body)
            ? ($body['message'] ?? $body['error']['message'] ?? $body['error_message'] ?? $body['detail'] ?? (is_string($body['error'] ?? null) ? $body['error'] : null) ?? $body['errors'][0]['details'] ?? $body['errors'][0]['message'] ?? null)
            : null;

        return is_string($message) && $message !== '' ? ': '.mb_substr($message, 0, 200) : '';
    }
}
