<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request/error-handling for the GitHub node family
 * (docs/NODES_CATALOG.md's "Priority 1" list). Resolves the access token via
 * `ResolvesConnectorCredential`: a workspace-scoped `ConnectorCredential`
 * referenced by `config['credential_id']`, or a plain `access_token` config
 * field for nodes configured before `ConnectorCredential` existed.
 */
abstract class AbstractGitHubNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://api.github.com';

    public function category(): string
    {
        return 'github';
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
    protected function patch(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $params);
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
    protected function delete(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'delete', $endpoint, $config, $params);
    }

    /**
     * The number of the last page of a list, read from GitHub's `Link` header.
     * A list that fits one page has no header and so one page.
     *
     * @param  array<string, mixed>  $config
     */
    protected function lastPage(Run $run, string $endpoint, array $config, int $perPage): int
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->get(self::BASE_URL.$endpoint, ['per_page' => $perPage, 'page' => 1]);

        if ($response->failed()) {
            throw new RuntimeException("GitHub API error [{$endpoint}]: ".($response->json('message') ?? $response->body()));
        }

        return preg_match('/[?&]page=(\d+)[^>]*>;\s*rel="last"/', (string) $response->header('Link'), $match) === 1 ? (int) $match[1] : 1;
    }

    /**
     * The `owner/name` of a repository, checked so it can only name one: it is
     * put straight into the request path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function repo(array $config): string
    {
        $repo = trim((string) ($config['repo'] ?? ''));

        // Two names of letters, digits, `_`, `.` and `-`, neither of them only dots (`..` would climb out of /repos).
        if (preg_match('#^(?!\.+/)[A-Za-z0-9_.-]+/(?!\.+$)[A-Za-z0-9_.-]+$#', $repo) !== 1) {
            throw new InvalidArgumentException('GitHub: the repository must look like owner/name.');
        }

        return $repo;
    }

    /**
     * A path segment (a branch, a file path) encoded for the URL, keeping the
     * slashes of a path.
     */
    protected function segment(string $value): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $value)));
    }

    /**
     * GitHub signals failure via a normal HTTP status code, with the error
     * detail under a top-level `message` in the JSON body. `null` params
     * (an unset optional filter) are dropped rather than sent through, since
     * an empty query string value is not the same as "omit this filter".
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token)->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28']);
        $params = array_filter($params, fn ($value) => $value !== null);

        // A body is `{}` when empty: `[]` is not a JSON object. A GET with no
        // parameters passes none, so a query string already in the endpoint stays.
        $body = fn (): string => json_encode($params === [] ? new \stdClass : $params, JSON_THROW_ON_ERROR);

        $response = match ($method) {
            'get' => $params === [] ? $request->get(self::BASE_URL.$endpoint) : $request->get(self::BASE_URL.$endpoint, $params),
            'patch' => $request->withBody($body(), 'application/json')->patch(self::BASE_URL.$endpoint),
            'put' => $request->withBody($body(), 'application/json')->put(self::BASE_URL.$endpoint),
            'delete' => $request->withBody($params === [] ? '' : $body(), 'application/json')->delete(self::BASE_URL.$endpoint),
            default => $request->withBody($body(), 'application/json')->post(self::BASE_URL.$endpoint),
        };

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->body();

            throw new RuntimeException("GitHub API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
