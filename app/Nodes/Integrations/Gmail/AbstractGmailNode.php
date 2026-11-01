<?php

namespace App\Nodes\Integrations\Gmail;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request/error-handling for the Gmail node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). Resolves the access token via
 * `ResolvesConnectorCredential`: a workspace-scoped `ConnectorCredential`
 * referenced by `config['credential_id']`, or a plain `access_token` config
 * field for nodes configured before `ConnectorCredential` existed.
 */
abstract class AbstractGmailNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function category(): string
    {
        return 'gmail';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function put(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'put', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $params);
    }

    /**
     * Gmail answers a delete with an empty 204, so it comes back as an empty array.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config): array
    {
        return $this->call($run, 'delete', $endpoint, $config, []);
    }

    /**
     * An id from a setting, ready to sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? rawurlencode($value) : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * Unlike Slack, Gmail signals failure via a normal HTTP status code, with
     * the error detail under `error.message` in the JSON body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token);
        // An empty `query` array would replace a query string already in the endpoint.
        $response = match ($method) {
            'get' => $params === [] ? $request->get(self::BASE_URL.$endpoint) : $request->get(self::BASE_URL.$endpoint, $params),
            'delete' => $request->delete(self::BASE_URL.$endpoint),
            'put' => $request->asJson()->put(self::BASE_URL.$endpoint, $params),
            'patch' => $request->asJson()->patch(self::BASE_URL.$endpoint, $params),
            default => $request->asJson()->post(self::BASE_URL.$endpoint, $params),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Gmail API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
