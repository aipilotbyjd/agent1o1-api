<?php

namespace App\Services\Assistant\Computer;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Billing\Feature;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Assistant\AssistantSession;
use Closure;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The cloud computer behind a conversation: started on first use, kept
 * alive while used, and started fresh (files gone) if the provider deleted
 * it while idle. Billed per minute of running code.
 */
class Sandboxes
{
    public function __construct(
        private readonly SandboxDriver $driver,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    /**
     * Configured on this server, and on the workspace's plan.
     */
    public function available(Assistant $assistant): bool
    {
        return filled(config('assistant.sandbox.api_key'))
            && (bool) $assistant->workspace->currentPlan()?->hasFeature(Feature::AssistantSandbox);
    }

    /**
     * @return array{0: SandboxResult, 1: bool} the result, and whether a fresh computer had to be started
     */
    public function run(AssistantSession $session, string $language, string $code): array
    {
        [$result, $fresh] = $this->withSandbox($session, fn (AssistantSandbox $sandbox): SandboxResult => $this->driver->run(
            $sandbox->provider_sandbox_id,
            $sandbox->access_token,
            $language,
            $code,
            (int) config('assistant.sandbox.run_timeout_seconds'),
        ));

        $this->charge($session, $result->seconds);

        return [$result, $fresh];
    }

    public function readFile(AssistantSession $session, string $path): string
    {
        $sandbox = $session->sandbox()->first() ?? throw new RuntimeException('Nothing has run in this conversation yet, so there are no files.');

        return $this->driver->readFile($sandbox->provider_sandbox_id, $sandbox->access_token, $path, (int) config('assistant.sandbox.max_file_kilobytes') * 1024);
    }

    /**
     * @template T
     *
     * @param  Closure(AssistantSandbox): T  $work
     * @return array{0: T, 1: bool}
     */
    private function withSandbox(AssistantSession $session, Closure $work): array
    {
        $idle = (int) config('assistant.sandbox.idle_seconds');
        $sandbox = $session->sandbox()->first();
        $fresh = $sandbox === null;

        if ($sandbox !== null) {
            try {
                $this->driver->keepAlive($sandbox->provider_sandbox_id, $idle);
            } catch (SandboxGoneException) {
                $sandbox->delete();
                $sandbox = null;
                $fresh = true;
            }
        }

        $sandbox ??= $this->start($session, $idle);

        try {
            $result = $work($sandbox);
        } catch (SandboxGoneException) {
            $sandbox->delete();
            $result = $work($this->start($session, $idle));
            $fresh = true;
        }

        $sandbox = $session->sandbox()->first();
        $sandbox?->forceFill(['last_used_at' => now()])->save();

        return [$result, $fresh];
    }

    private function start(AssistantSession $session, int $idle): AssistantSandbox
    {
        $created = $this->driver->create($idle);

        return AssistantSandbox::query()->create([
            'assistant_id' => $session->assistant_id,
            'assistant_session_id' => $session->id,
            'provider' => config('assistant.sandbox.provider'),
            'provider_sandbox_id' => $created['id'],
            'access_token' => $created['access_token'],
            'last_used_at' => now(),
        ]);
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
