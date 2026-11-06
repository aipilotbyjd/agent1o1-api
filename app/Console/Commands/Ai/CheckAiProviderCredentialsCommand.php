<?php

namespace App\Console\Commands\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Ai\CheckAiProviderCredentialJob;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Console\Command;

class CheckAiProviderCredentialsCommand extends Command
{
    protected $signature = 'ai-credentials:check {--hours= : Re-check keys not checked within this many hours (default: config byok.recheck_after_hours)}';

    protected $description = 'Queue a provider check for every AI provider key that has not been checked recently.';

    /**
     * Skips keys already known to be rejected: they can't pass until someone
     * replaces them, and whoever relies on them has been told. Skips keys for
     * a provider a super admin has switched off: they aren't being used.
     */
    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('byok.recheck_after_hours'));
        $staleBefore = now()->subHours($hours);
        $count = 0;

        AiProviderCredential::query()
            ->where('validation_status', '!=', AiProviderCredentialStatus::Invalid->value)
            ->whereIn('execution_provider', app(AiProviderSettings::class)->enabledProviders())
            ->where(fn ($query) => $query->whereNull('last_validated_at')->orWhere('last_validated_at', '<', $staleBefore))
            ->each(function (AiProviderCredential $credential) use (&$count): void {
                CheckAiProviderCredentialJob::dispatch($credential);
                $count++;
            });

        $this->info("Queued {$count} AI provider key check(s).");

        return self::SUCCESS;
    }
}
