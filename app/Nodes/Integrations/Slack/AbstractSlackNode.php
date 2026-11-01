<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Slack node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). Resolves the access token via
 * `ResolvesConnectorCredential`: a workspace-scoped `ConnectorCredential`
 * referenced by `config['credential_id']`, or a plain `access_token` config
 * field for nodes configured before `ConnectorCredential` existed.
 */
abstract class AbstractSlackNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://slack.com/api/';

    public function category(): string
    {
        return 'slack';
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
     * Slack's API always answers 200 and signals failure via a body-level
     * `ok: false` + `error` code, not an HTTP status, so a normal
     * `$response->throw()` would never catch a Slack-side failure.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token);
        $response = $method === 'get'
            ? $request->get(self::BASE_URL.$endpoint, $params)
            : $request->asJson()->post(self::BASE_URL.$endpoint, $params);

        $body = $response->json() ?? [];

        if (($body['ok'] ?? false) !== true) {
            throw new RuntimeException('Slack API error ['.$endpoint.']: '.($body['error'] ?? 'unknown_error'));
        }

        return $body;
    }
}
