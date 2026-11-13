<?php

namespace App\Services\Sandbox\Drivers;

use App\Services\Sandbox\AbstractSandboxDriver;
use App\Services\Sandbox\SandboxGoneException;
use App\Services\Sandbox\SandboxResult;
use App\Services\Sandbox\SandboxStartException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * E2B code-interpreter sandboxes over their HTTP API: the control plane
 * creates and keeps sandboxes alive; each sandbox serves code execution
 * (port 49999, NDJSON stream) and its filesystem (port 49983).
 */
class E2BSandboxDriver extends AbstractSandboxDriver
{
    private const int CODE_PORT = 49999;

    private const int FILES_PORT = 49983;

    public function create(int $idleSeconds): array
    {
        try {
            $response = $this->api()->post('/sandboxes', [
                'templateID' => $this->provider->setting('template', 'code-interpreter-v1'),
                'timeout' => $idleSeconds,
            ]);
        } catch (ConnectionException $e) {
            throw new SandboxStartException('Could not reach E2B: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new SandboxStartException("E2B could not start a sandbox (HTTP {$response->status()}).");
        }

        $this->ensureOk($response, 'create sandbox');

        return [
            'id' => (string) $response->json('sandboxID'),
            'access_token' => $response->json('envdAccessToken'),
        ];
    }

    public function run(string $id, ?string $accessToken, string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $started = microtime(true);

        $response = $this->sandbox($accessToken)
            ->timeout($timeoutSeconds + 10)
            ->post($this->host($id, self::CODE_PORT).'/execute', ['code' => $code, 'language' => $language]);

        $this->ensureAlive($response);
        $this->ensureOk($response, 'run code');

        $stdout = $stderr = '';
        $results = [];
        $error = null;

        foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
            $event = json_decode($line, true);

            if (! is_array($event)) {
                continue;
            }

            match ($event['type'] ?? null) {
                'stdout' => $stdout .= (string) ($event['text'] ?? ''),
                'stderr' => $stderr .= (string) ($event['text'] ?? ''),
                'result' => $results[] = (string) ($event['text'] ?? (isset($event['png']) ? '[image]' : '')),
                'error' => $error = trim(($event['name'] ?? 'Error').': '.($event['value'] ?? '')."\n".($event['traceback'] ?? '')),
                default => null,
            };
        }

        return new SandboxResult($stdout, $stderr, array_values(array_filter($results)), $error, (int) ceil(microtime(true) - $started));
    }

    public function readFile(string $id, ?string $accessToken, string $path, int $maxBytes): string
    {
        $response = $this->sandbox($accessToken)->timeout(60)->get($this->host($id, self::FILES_PORT).'/files', ['path' => $path]);

        $this->ensureAlive($response);

        if ($response->status() === 404) {
            throw new RuntimeException("There is no file at {$path}.");
        }

        $this->ensureOk($response, 'read file');

        if (strlen($response->body()) > $maxBytes) {
            throw new RuntimeException('The file is too large to save.');
        }

        return $response->body();
    }

    public function keepAlive(string $id, int $idleSeconds): void
    {
        $response = $this->api()->post("/sandboxes/{$id}/timeout", ['timeout' => $idleSeconds]);

        $this->ensureAlive($response);
    }

    public function destroy(string $id, ?string $accessToken): void
    {
        $response = $this->api()->delete("/sandboxes/{$id}");

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException("E2B could not delete the sandbox (HTTP {$response->status()}).");
        }
    }

    public function runOnce(string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $created = $this->create($timeoutSeconds + 30);

        try {
            return $this->run($created['id'], $created['access_token'], $language, $code, $timeoutSeconds);
        } finally {
            try {
                $this->destroy($created['id'], $created['access_token']);
            } catch (Throwable) {
                // The sandbox expires by itself at the provider.
            }
        }
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl((string) $this->provider->setting('api_url', 'https://api.e2b.dev'))
            ->withHeaders(['X-API-Key' => (string) $this->provider->credential('api_key')])
            ->acceptJson()
            ->timeout(30);
    }

    private function sandbox(?string $accessToken): PendingRequest
    {
        return Http::withHeaders(array_filter(['X-Access-Token' => $accessToken]));
    }

    private function host(string $id, int $port): string
    {
        return "https://{$port}-{$id}.".$this->provider->setting('domain', 'e2b.app');
    }

    private function ensureAlive(Response $response): void
    {
        if (in_array($response->status(), [404, 502], true) && ! str_contains($response->body(), 'file')) {
            throw new SandboxGoneException('The sandbox is gone.');
        }
    }

    private function ensureOk(Response $response, string $what): void
    {
        if ($response->failed()) {
            throw new RuntimeException("Sandbox failed to {$what}: ".($response->json('message') ?? $response->body()));
        }
    }
}
