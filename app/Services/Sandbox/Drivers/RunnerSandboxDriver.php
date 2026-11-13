<?php

namespace App\Services\Sandbox\Drivers;

use App\Services\Sandbox\AbstractSandboxDriver;
use App\Services\Sandbox\SandboxGoneException;
use App\Services\Sandbox\SandboxResult;
use App\Services\Sandbox\SandboxStartException;
use App\Services\Sandbox\SandboxUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The operator's own sandbox servers, run on separate machines (for example
 * hardened gVisor or Firecracker containers behind a load balancer). The
 * protocol is small so any runner can implement it. Every request carries
 * `Authorization: Bearer <token>`:
 *
 *   POST   /v1/sandboxes                {idle_seconds}              → {id, access_token?}
 *   POST   /v1/sandboxes/{id}/run       {language, code, timeout}   → {stdout, stderr, results[], error, seconds}
 *   GET    /v1/sandboxes/{id}/files?path=                           → file bytes (404 when missing)
 *   POST   /v1/sandboxes/{id}/keepalive {idle_seconds}
 *   DELETE /v1/sandboxes/{id}
 *   POST   /v1/run                      {language, code, timeout}   → same as a run, on a throwaway sandbox
 *
 * A 404 on a sandbox means it is gone; 429 and 5xx on create or one-shot
 * mean the runner is busy or down and another provider may be tried.
 */
class RunnerSandboxDriver extends AbstractSandboxDriver
{
    private bool $checkedUrl = false;

    public function create(int $idleSeconds): array
    {
        try {
            $response = $this->api()->post('/v1/sandboxes', ['idle_seconds' => $idleSeconds]);
        } catch (ConnectionException $e) {
            throw new SandboxStartException('Could not reach the runner: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new SandboxStartException("The runner could not start a sandbox (HTTP {$response->status()}).");
        }

        $this->ensureOk($response, 'create sandbox');

        return ['id' => (string) $response->json('id'), 'access_token' => $response->json('access_token')];
    }

    public function run(string $id, ?string $accessToken, string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $response = $this->api($accessToken)
            ->timeout($timeoutSeconds + 15)
            ->post("/v1/sandboxes/{$id}/run", ['language' => $language, 'code' => $code, 'timeout' => $timeoutSeconds]);

        $this->ensureAlive($response);
        $this->ensureOk($response, 'run code');

        return $this->result($response);
    }

    public function readFile(string $id, ?string $accessToken, string $path, int $maxBytes): string
    {
        $response = $this->api($accessToken)->timeout(60)->get("/v1/sandboxes/{$id}/files", ['path' => $path]);

        if ($response->status() === 404 && str_contains(strtolower($response->body()), 'file')) {
            throw new RuntimeException("There is no file at {$path}.");
        }

        $this->ensureAlive($response);
        $this->ensureOk($response, 'read file');

        if (strlen($response->body()) > $maxBytes) {
            throw new RuntimeException('The file is too large to save.');
        }

        return $response->body();
    }

    public function keepAlive(string $id, int $idleSeconds): void
    {
        $this->ensureAlive($this->api()->post("/v1/sandboxes/{$id}/keepalive", ['idle_seconds' => $idleSeconds]));
    }

    public function destroy(string $id, ?string $accessToken): void
    {
        $response = $this->api($accessToken)->delete("/v1/sandboxes/{$id}");

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException("The runner could not delete the sandbox (HTTP {$response->status()}).");
        }
    }

    public function runOnce(string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        try {
            $response = $this->api()->timeout($timeoutSeconds + 15)->post('/v1/run', ['language' => $language, 'code' => $code, 'timeout' => $timeoutSeconds]);
        } catch (ConnectionException $e) {
            throw new SandboxStartException('Could not reach the runner: '.$e->getMessage(), previous: $e);
        }

        if (in_array($response->status(), [429, 502, 503, 504], true)) {
            throw new SandboxStartException("The runner is busy (HTTP {$response->status()}).");
        }

        $this->ensureOk($response, 'run code');

        return $this->result($response);
    }

    private function result(Response $response): SandboxResult
    {
        return new SandboxResult(
            (string) $response->json('stdout', ''),
            (string) $response->json('stderr', ''),
            array_values(array_map('strval', (array) $response->json('results', []))),
            filled($response->json('error')) ? (string) $response->json('error') : null,
            (int) $response->json('seconds', 0),
        );
    }

    private function api(?string $accessToken = null): PendingRequest
    {
        $url = (string) $this->provider->setting('url');

        if (! $this->checkedUrl) {
            $problem = app(SandboxUrlGuard::class)->problem($url);

            if ($problem !== null) {
                throw new SandboxStartException("The runner address is not allowed: {$problem}");
            }

            $this->checkedUrl = true;
        }

        return Http::baseUrl(rtrim($url, '/'))
            ->withToken((string) $this->provider->credential('token'))
            ->withHeaders(array_filter(['X-Sandbox-Token' => $accessToken]))
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(30);
    }

    private function ensureAlive(Response $response): void
    {
        if ($response->status() === 404 || $response->status() === 410) {
            throw new SandboxGoneException('The sandbox is gone.');
        }
    }

    private function ensureOk(Response $response, string $what): void
    {
        if ($response->failed()) {
            throw new RuntimeException("Runner failed to {$what}: ".($response->json('message') ?? $response->body()));
        }
    }
}
