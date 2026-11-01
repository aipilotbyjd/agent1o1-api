<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Requests to a Google JSON API with the connected account's token, and
 * Google's error message when one fails.
 *
 * Google signals failure with an HTTP status and the detail under
 * `error.message`. A successful delete has an empty body, which comes back
 * as an empty array.
 */
trait CallsGoogleJsonApi
{
    use ResolvesConnectorCredential;

    /**
     * The API's root, without a trailing slash.
     */
    abstract protected function baseUrl(): string;

    /**
     * Shown in error messages, e.g. "Google Tasks".
     */
    abstract protected function appName(): string;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are left out
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $query = []): array
    {
        $query = array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '');

        // An empty `query` array would replace a query string already in the endpoint.
        $request = $this->request($run, $config);

        return $this->parse($query === [] ? $request->get($this->baseUrl().$endpoint) : $request->get($this->baseUrl().$endpoint, $query), $endpoint);
    }

    /**
     * A response that is not JSON (a caption track, say), as text.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are left out
     *
     * @throws RuntimeException when Google answers with an error status
     */
    protected function getText(Run $run, string $endpoint, array $config, array $query = []): string
    {
        $response = $this->request($run, $config)->get($this->baseUrl().$endpoint, array_filter($query, fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("{$this->appName()} API error [{$endpoint}]: {$message}");
        }

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $body = []): array
    {
        return $this->parse($this->request($run, $config)->withBody($this->json($body), 'application/json')->post($this->baseUrl().$endpoint), $endpoint);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $body = []): array
    {
        return $this->parse($this->request($run, $config)->withBody($this->json($body), 'application/json')->patch($this->baseUrl().$endpoint), $endpoint);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function put(Run $run, string $endpoint, array $config, array $body = []): array
    {
        return $this->parse($this->request($run, $config)->withBody($this->json($body), 'application/json')->put($this->baseUrl().$endpoint), $endpoint);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config): array
    {
        return $this->parse($this->request($run, $config)->delete($this->baseUrl().$endpoint), $endpoint);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function request(Run $run, array $config): PendingRequest
    {
        return Http::withToken($this->resolveAccessToken($run, $config))->acceptJson();
    }

    /**
     * A body as JSON, with an empty one as `{}`: `[]` is not a JSON object.
     *
     * @param  array<string, mixed>  $body
     */
    private function json(array $body): string
    {
        return json_encode($body === [] ? new \stdClass : $body, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException when Google answers with an error status
     */
    private function parse(Response $response, string $endpoint): array
    {
        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("{$this->appName()} API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
