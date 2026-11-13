<?php

namespace App\Services\Assistant\Computer;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Billing\Feature;
use App\Enums\Sandbox\SandboxMode;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Assistant\AssistantSession;
use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\SandboxDriver;
use App\Services\Sandbox\SandboxGoneException;
use App\Services\Sandbox\SandboxHealth;
use App\Services\Sandbox\SandboxManager;
use App\Services\Sandbox\SandboxProviders;
use App\Services\Sandbox\SandboxResult;
use App\Services\Sandbox\SandboxRouter;
use App\Services\Sandbox\SandboxRunLog;
use App\Services\Sandbox\SandboxUnavailableException;
use Closure;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The cloud computer behind a conversation: started on first use, kept
 * alive while used, and started fresh (files gone) if the provider deleted
 * it while idle. Billed per minute of running code.
 *
 * Which provider hosts a computer is the router's choice at the moment it
 * starts; the computer stays on that provider for its life, whatever the
 * default becomes later.
 */
class Sandboxes
{
    public function __construct(
        private readonly SandboxRouter $router,
        private readonly SandboxProviders $providers,
        private readonly SandboxManager $manager,
        private readonly SandboxHealth $health,
        private readonly SandboxRunLog $log,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    /**
     * A provider can host a computer right now, and the workspace's plan includes it.
     */
    public function available(Assistant $assistant): bool
    {
        return (bool) $assistant->workspace->currentPlan()?->hasFeature(Feature::AssistantSandbox)
            && $this->router->available(SandboxMode::Persistent);
    }

    /**
     * @return array{0: SandboxResult, 1: bool} the result, and whether a fresh computer had to be started
     */
    public function run(AssistantSession $session, string $language, string $code): array
    {
        [$result, $fresh, $providerKey] = $this->withSandbox($session, fn (AssistantSandbox $sandbox, SandboxDriver $driver): SandboxResult => $driver->run(
            $sandbox->provider_sandbox_id,
            $sandbox->access_token,
            $language,
            $code,
            (int) config('assistant.sandbox.run_timeout_seconds'),
        ));

        $this->charge($session, $result->seconds);
        $this->record($session, $providerKey, $language, $result);

        return [$result, $fresh];
    }

    public function readFile(AssistantSession $session, string $path): string
    {
        $sandbox = $session->sandbox()->first() ?? throw new RuntimeException('Nothing has run in this conversation yet, so there are no files.');
        $driver = $this->driverFor($sandbox) ?? throw new RuntimeException('That computer is no longer available, so its files are gone.');

        return $driver->readFile($sandbox->provider_sandbox_id, $sandbox->access_token, $path, (int) config('assistant.sandbox.max_file_kilobytes') * 1024);
    }

    /**
     * Deletes a computer at its provider (best effort) and forgets it.
     */
    public function destroy(AssistantSandbox $sandbox): void
    {
        try {
            $this->driverFor($sandbox, allowDisabled: true)?->destroy($sandbox->provider_sandbox_id, $sandbox->access_token);
        } catch (Throwable) {
            // It expires at the provider on its own.
        }

        $sandbox->delete();
    }

    /**
     * @template T
     *
     * @param  Closure(AssistantSandbox, SandboxDriver): T  $work
     * @return array{0: T, 1: bool, 2: string} the result, whether a fresh computer was needed, and the provider's key
     */
    private function withSandbox(AssistantSession $session, Closure $work): array
    {
        $idle = (int) config('assistant.sandbox.idle_seconds');
        $sandbox = $session->sandbox()->first();
        $driver = null;
        $fresh = $sandbox === null;

        if ($sandbox !== null) {
            try {
                $driver = $this->driverFor($sandbox) ?? throw new SandboxGoneException('The provider is no longer in use.');
                $driver->keepAlive($sandbox->provider_sandbox_id, $idle);
            } catch (SandboxGoneException) {
                $sandbox->delete();
                $sandbox = null;
                $fresh = true;
            }
        }

        if ($sandbox === null) {
            [$sandbox, $driver] = $this->start($session, $idle);
        }

        try {
            $result = $work($sandbox, $driver);
        } catch (SandboxGoneException) {
            $sandbox->delete();
            [$sandbox, $driver] = $this->start($session, $idle);
            $result = $work($sandbox, $driver);
            $fresh = true;
        }

        $session->sandbox()->first()?->forceFill(['last_used_at' => now()])->save();

        return [$result, $fresh, $sandbox->provider];
    }

    /**
     * @return array{0: AssistantSandbox, 1: SandboxDriver}
     */
    private function start(AssistantSession $session, int $idle): array
    {
        $candidates = $this->router->candidates(SandboxMode::Persistent);

        if ($candidates->isEmpty()) {
            throw new SandboxUnavailableException($this->router->stopReason() ?? 'Running code is not set up on this platform.');
        }

        $last = null;

        foreach ($candidates as $provider) {
            $driver = $this->manager->driver($provider);

            try {
                $created = $driver->create($idle);
            } catch (Throwable $e) {
                $this->health->failed($provider, $e);
                $last = $e;

                continue;
            }

            $this->health->succeeded($provider);

            $sandbox = AssistantSandbox::query()->create([
                'assistant_id' => $session->assistant_id,
                'assistant_session_id' => $session->id,
                'provider' => $provider->key,
                'provider_sandbox_id' => $created['id'],
                'access_token' => $created['access_token'],
                'last_used_at' => now(),
            ]);

            return [$sandbox, $driver];
        }

        throw new SandboxUnavailableException('The cloud computer could not be started right now. Try again shortly.', previous: $last);
    }

    private function driverFor(AssistantSandbox $sandbox, bool $allowDisabled = false): ?SandboxDriver
    {
        $provider = $this->providers->find($sandbox->provider);

        if (! $provider instanceof SandboxProvider || (! $provider->is_enabled && ! $allowDisabled)) {
            return null;
        }

        return $this->manager->driver($provider);
    }

    private function record(AssistantSession $session, string $providerKey, string $language, SandboxResult $result): void
    {
        $provider = $this->providers->find($providerKey);

        if ($provider instanceof SandboxProvider) {
            $this->log->record($session->assistant->workspace, $provider, 'assistant', SandboxMode::Persistent, $language, $result->error === null ? 'ok' : 'error', $result->seconds, $result->error);
        }
    }

    private function charge(AssistantSession $session, int $seconds): void
    {
        $sandbox = $session->sandbox()->first();
        $sandbox?->increment('seconds_used', $seconds);

        $credits = (int) ceil(max($seconds, 1) / 60) * (int) config('assistant.sandbox.credits_per_minute');

        // Charges are idempotent per source id, so each run gets its own.
        if ($credits > 0 && $sandbox !== null) {
            $this->deductCredits->execute(
                $session->assistant->workspace,
                CreditTransactionType::AssistantSandbox,
                (string) Str::uuid(),
                $credits,
                'Assistant computer ('.max(1, (int) ceil($seconds / 60)).' min)',
                allowOverdraft: true,
            );
        }
    }
}
