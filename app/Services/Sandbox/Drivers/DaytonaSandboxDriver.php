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
use Throwable;

/**
 * Daytona sandboxes (daytona.io, or your own Daytona install). The control
 * API creates, inspects and deletes sandboxes (`POST/GET/DELETE /sandbox`);
 * each sandbox is driven through the toolbox proxy
 * (`{toolbox}/{id}/process/execute`, `{toolbox}/{id}/files/download`). Both
 * take `Authorization: Bearer <API key>`.
 *
 * The toolbox runs a shell command and returns one combined output string,
 * so a run writes the code to a file and runs it with `timeout`. Unlike a
 * code-interpreter kernel, variables do not carry over between runs; files
 * do. Sandboxes stop themselves after the idle time and are then deleted
 * (`autoDeleteInterval` 0), so a forgotten one does not keep costing money.
 *
 * Config: `api_url` (default Daytona Cloud), `toolbox_url` (default the
 * Cloud proxy), `snapshot`, `target` (region), `network_block_all`.
 */
class DaytonaSandboxDriver extends AbstractSandboxDriver
{
    private const string DEFAULT_API_URL = 'https://app.daytona.io/api';

    private const string DEFAULT_TOOLBOX_URL = 'https://proxy.app.daytona.io/toolbox';

    /**
     * States that mean the sandbox will not run code again.
     */
    private const array GONE_STATES = ['destroyed', 'destroying', 'stopped', 'stopping', 'archived', 'archiving', 'error', 'build_failed'];

    private const int START_ATTEMPTS = 20;

    private const int MAX_ENCODED_CODE_BYTES = 100000;

    private bool $checkedUrls = false;

    public function create(int $idleSeconds): array
    {
        try {
            $response = $this->api()->post('/sandbox', array_filter([
                'snapshot' => $this->provider->setting('snapshot'),
                'target' => $this->provider->setting('target'),
                'autoStopInterval' => max(1, (int) ceil($idleSeconds / 60)),
                'autoDeleteInterval' => 0,
                'networkBlockAll' => $this->provider->setting('network_block_all'),
                'labels' => ['app' => 'agent1o1'],
            ], fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException $e) {
            throw new SandboxStartException('Could not reach Daytona: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new SandboxStartException("Daytona could not start a sandbox (HTTP {$response->status()}).");
        }

        $this->ensureOk($response, 'create sandbox');

        $id = (string) $response->json('id');
        $state = strtolower((string) $response->json('state'));

        for ($attempt = 0; $state !== '' && $state !== 'started'; $attempt++) {
            if (in_array($state, self::GONE_STATES, true) || $attempt >= self::START_ATTEMPTS) {
                $this->forget($id);

                throw new SandboxStartException("The Daytona sandbox did not start (state: {$state}).");
            }

            sleep(1);

            $state = strtolower((string) $this->api()->get("/sandbox/{$id}")->json('state'));
        }

        return ['id' => $id, 'access_token' => null];
    }

    public function run(string $id, ?string $accessToken, string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $started = microtime(true);

        $response = $this->toolbox()
            ->timeout($timeoutSeconds + 15)
            ->post("/{$id}/process/execute", ['command' => $this->command($language, $code, $timeoutSeconds)]);

        $this->ensureAlive($response);
        $this->ensureOk($response, 'run code');

        $exitCode = $response->json('exitCode');
        $output = (string) $response->json('result', '');

        $error = match (true) {
            $exitCode === 124 => 'The code ran out of time.',
            is_int($exitCode) && $exitCode !== 0 => "Exited with code {$exitCode}: ".trim(mb_substr($output, -1000)),
            default => null,
        };

        return new SandboxResult($output, '', [], $error, (int) ceil(microtime(true) - $started));
    }

    public function readFile(string $id, ?string $accessToken, string $path, int $maxBytes): string
    {
        $response = $this->toolbox()->timeout(60)->get("/{$id}/files/download", ['path' => $path]);

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

    /**
     * Checks the sandbox still exists and is running. Every run already
     * counts as activity, so no separate "extend" call is needed.
     */
    public function keepAlive(string $id, int $idleSeconds): void
    {
        $response = $this->api()->get("/sandbox/{$id}");

        $this->ensureAlive($response);

        if (in_array(strtolower((string) $response->json('state')), self::GONE_STATES, true)) {
            throw new SandboxGoneException('The sandbox is not running.');
        }
    }

    public function destroy(string $id, ?string $accessToken): void
    {
        $response = $this->api()->delete("/sandbox/{$id}");

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException("Daytona could not delete the sandbox (HTTP {$response->status()}).");
        }
    }

    public function runOnce(string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $created = $this->create($timeoutSeconds + 60);

        try {
            return $this->run($created['id'], null, $language, $code, $timeoutSeconds);
        } finally {
            $this->forget($created['id']);
        }
    }

    /**
     * A shell line that writes the code to a file (as base64, so nothing in
     * it can break the command) and runs it under `timeout`.
     */
    private function command(string $language, string $code, int $timeoutSeconds): string
    {
        [$program, $extension] = match ($language) {
            'js' => ['node', 'js'],
            'bash' => ['bash', 'sh'],
            default => ['python3', 'py'],
        };

        $encoded = base64_encode($code);

        if (strlen($encoded) > self::MAX_ENCODED_CODE_BYTES) {
            throw new RuntimeException('The code is too long to send to Daytona.');
        }

        $file = "/tmp/agent1o1_run.{$extension}";

        return "echo {$encoded} | base64 -d > {$file} && timeout {$timeoutSeconds} {$program} {$file}";
    }

    private function forget(string $id): void
    {
        try {
            $this->destroy($id, null);
        } catch (Throwable) {
            // The sandbox stops and is deleted at the provider on its own.
        }
    }

    private function api(): PendingRequest
    {
        return $this->http((string) $this->provider->setting('api_url', self::DEFAULT_API_URL))->timeout(30);
    }

    private function toolbox(): PendingRequest
    {
        return $this->http((string) $this->provider->setting('toolbox_url', self::DEFAULT_TOOLBOX_URL));
    }

    private function http(string $baseUrl): PendingRequest
    {
        $this->guardCustomUrls();

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withToken((string) $this->provider->credential('api_key'))
            ->acceptJson()
            ->withoutRedirecting();
    }

    /**
     * Addresses the operator typed in (a self-hosted Daytona) must be a
     * separate public machine. The defaults are Daytona Cloud.
     */
    private function guardCustomUrls(): void
    {
        if ($this->checkedUrls) {
            return;
        }

        foreach (['api_url', 'toolbox_url'] as $key) {
            $url = $this->provider->setting($key);

            if ($url === null) {
                continue;
            }

            $problem = app(SandboxUrlGuard::class)->problem((string) $url);

            if ($problem !== null) {
                throw new SandboxStartException("The Daytona {$key} is not allowed: {$problem}");
            }
        }

        $this->checkedUrls = true;
    }

    private function ensureAlive(Response $response): void
    {
        $stopped = $response->status() === 400 && preg_match('/not (running|started)|stopped/i', $response->body()) === 1;

        if (in_array($response->status(), [404, 410], true) || $stopped) {
            throw new SandboxGoneException('The sandbox is gone.');
        }
    }

    private function ensureOk(Response $response, string $what): void
    {
        if ($response->failed()) {
            throw new RuntimeException("Daytona failed to {$what}: ".($response->json('message') ?? $response->body()));
        }
    }
}
