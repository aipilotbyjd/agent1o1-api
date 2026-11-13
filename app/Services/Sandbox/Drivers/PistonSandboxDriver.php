<?php

namespace App\Services\Sandbox\Drivers;

use App\Services\Sandbox\AbstractSandboxDriver;
use App\Services\Sandbox\SandboxResult;
use App\Services\Sandbox\SandboxStartException;
use App\Services\Sandbox\SandboxUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A Piston server (github.com/engineer-man/piston) that the operator runs
 * on a separate machine. Piston runs one program per request in its own
 * isolated environment and keeps nothing afterwards, so it only does
 * one-shot runs. Keep it off this server and off the internet: let only
 * this app reach it.
 */
class PistonSandboxDriver extends AbstractSandboxDriver
{
    private const array LANGUAGES = ['python' => 'python', 'js' => 'javascript', 'bash' => 'bash'];

    private bool $checkedUrl = false;

    public function runOnce(string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        $started = microtime(true);

        try {
            $response = $this->api()->timeout($timeoutSeconds + 15)->post('/api/v2/execute', [
                'language' => self::LANGUAGES[$language] ?? $language,
                'version' => '*',
                'files' => [['content' => $code]],
                'run_timeout' => $timeoutSeconds * 1000,
                'compile_timeout' => 10000,
            ]);
        } catch (ConnectionException $e) {
            throw new SandboxStartException('Could not reach the Piston server: '.$e->getMessage(), previous: $e);
        }

        if (in_array($response->status(), [429, 502, 503, 504], true)) {
            throw new SandboxStartException("The Piston server is busy (HTTP {$response->status()}).");
        }

        if ($response->failed()) {
            throw new RuntimeException('Piston failed to run the code: '.($response->json('message') ?? $response->body()));
        }

        $compile = (array) $response->json('compile', []);
        $run = (array) $response->json('run', []);

        $error = null;

        if (($compile['code'] ?? 0) !== 0 && $compile !== []) {
            $error = trim('Compile error: '.($compile['stderr'] ?? $compile['output'] ?? ''));
        } elseif (($run['code'] ?? 0) !== 0) {
            $error = trim('Exited with code '.($run['code'] ?? '?').(filled($run['signal'] ?? null) ? ' ('.$run['signal'].')' : '').': '.($run['stderr'] ?? ''));
        }

        return new SandboxResult(
            (string) ($run['stdout'] ?? ''),
            (string) ($run['stderr'] ?? ''),
            [],
            $error,
            (int) ceil(microtime(true) - $started),
        );
    }

    private function api(): PendingRequest
    {
        $url = (string) $this->provider->setting('url');

        if (! $this->checkedUrl) {
            $problem = app(SandboxUrlGuard::class)->problem($url);

            if ($problem !== null) {
                throw new SandboxStartException("The Piston address is not allowed: {$problem}");
            }

            $this->checkedUrl = true;
        }

        $request = Http::baseUrl(rtrim($url, '/'))->acceptJson()->withoutRedirecting();

        if (($token = $this->provider->credential('token')) !== null) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
