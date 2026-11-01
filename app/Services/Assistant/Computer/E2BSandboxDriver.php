<?php

namespace App\Services\Assistant\Computer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * E2B code-interpreter sandboxes over their HTTP API: the control plane
 * creates and keeps sandboxes alive; each sandbox serves code execution
 * (port 49999, NDJSON stream) and its filesystem (port 49983).
 */
class E2BSandboxDriver implements SandboxDriver
{
    private const int CODE_PORT = 49999;

    private const int FILES_PORT = 49983;

    public function create(int $idleSeconds): array
    {
        $response = $this->api()->post('/sandboxes', [
            'templateID' => config('assistant.sandbox.template'),
            'timeout' => $idleSeconds,
        ]);

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

    private function api(): PendingRequest
    {
        return Http::baseUrl((string) config('assistant.sandbox.api_url'))
            ->withHeaders(['X-API-Key' => (string) config('assistant.sandbox.api_key')])
            ->acceptJson()
            ->timeout(30);
    }

    private function sandbox(?string $accessToken): PendingRequest
    {
        return Http::withHeaders(array_filter(['X-Access-Token' => $accessToken]));
    }

    private function host(string $id, int $port): string
    {
        return "https://{$port}-{$id}.".config('assistant.sandbox.domain');
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
