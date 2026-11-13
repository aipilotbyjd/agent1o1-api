<?php

namespace App\Services\Sandbox;

use App\Enums\Sandbox\SandboxMode;
use App\Models\Workspaces\Workspace;
use Throwable;

/**
 * Runs a script once on a fresh remote environment for a workflow. Never
 * runs anything here: it picks a provider, takes one of its slots, sends
 * the code, records the cost and gives the slot back. If a provider cannot
 * be reached at all the next one is tried; if a run fails after code
 * started it is not repeated elsewhere.
 */
class CodeRunner
{
    public const string SOURCE = 'workflow';

    public function __construct(
        private readonly SandboxRouter $router,
        private readonly SandboxManager $manager,
        private readonly SandboxConcurrency $concurrency,
        private readonly SandboxHealth $health,
        private readonly SandboxRunLog $log,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{result: SandboxResult, provider: string}
     *
     * @throws SandboxUnavailableException
     */
    public function run(Workspace $workspace, string $language, string $code, array $input, int $timeoutSeconds): array
    {
        $this->guard($workspace);

        $candidates = $this->router->candidates(SandboxMode::OneShot);

        if ($candidates->isEmpty()) {
            throw new SandboxUnavailableException($this->router->stopReason() ?? 'Running code is not set up on this platform.');
        }

        $wrapped = CodeWrapper::withInput($language, $code, $input);
        $lastError = null;

        foreach ($candidates as $provider) {
            if (! $this->concurrency->enter($provider)) {
                continue;
            }

            try {
                $result = $this->manager->driver($provider)->runOnce($language, $wrapped, $timeoutSeconds);
            } catch (SandboxStartException $e) {
                $this->health->failed($provider, $e);
                $this->log->record($workspace, $provider, self::SOURCE, SandboxMode::OneShot, $language, 'failed', 0, $e->getMessage());
                $lastError = $e;

                continue;
            } catch (Throwable $e) {
                $this->health->failed($provider, $e);
                $this->log->record($workspace, $provider, self::SOURCE, SandboxMode::OneShot, $language, 'failed', 0, $e->getMessage());

                throw new SandboxUnavailableException('The code could not be run because the sandbox failed. Try again shortly.', previous: $e);
            } finally {
                $this->concurrency->leave($provider);
            }

            $this->health->succeeded($provider);
            $this->log->record($workspace, $provider, self::SOURCE, SandboxMode::OneShot, $language, $result->error === null ? 'ok' : 'error', $result->seconds, $result->error);

            return ['result' => $result, 'provider' => $provider->key];
        }

        throw new SandboxUnavailableException($lastError !== null
            ? 'No code provider could be reached right now. Try again shortly.'
            : 'Every code provider is busy right now. Try again shortly.', previous: $lastError);
    }

    /**
     * Whether the platform can run a one-shot script right now.
     */
    public function available(): bool
    {
        return $this->router->available(SandboxMode::OneShot);
    }

    /**
     * @throws SandboxUnavailableException
     */
    private function guard(Workspace $workspace): void
    {
        $cap = config('sandbox.code.workspace_daily_seconds');

        if ($cap !== null && $this->log->workspaceSecondsToday($workspace) >= (int) $cap) {
            throw new SandboxUnavailableException('This workspace has used its code running time for today. It resets at midnight.');
        }
    }
}
