<?php

namespace App\Console\Commands\Ai;

use App\Enums\Ai\AiProviderAuthMethod;
use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Ai\RefreshAiProviderTokenJob;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Console\Command;

class RefreshAiProviderTokensCommand extends Command
{
    protected $signature = 'ai-credentials:refresh-tokens';

    protected $description = 'Queue a renewal for every one-click AI provider connection whose token expires soon.';

    /**
     * Skips connections already known to be broken: they need reconnecting,
     * and whoever relies on them has been told. Skips connections for a
     * provider a super admin has switched off: they aren't being used.
     */
    public function handle(): int
    {
        $count = 0;

        AiProviderCredential::query()
            ->where('auth_method', AiProviderAuthMethod::OAuth->value)
            ->where('validation_status', '!=', AiProviderCredentialStatus::Invalid->value)
            ->whereIn('execution_provider', app(AiProviderSettings::class)->enabledProviders())
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addSeconds((int) config('byok.connect.refresh_before_seconds', 900)))
            ->each(function (AiProviderCredential $credential) use (&$count): void {
                RefreshAiProviderTokenJob::dispatch($credential);
                $count++;
            });

        $this->info("Queued {$count} AI provider token renewal(s).");

        return self::SUCCESS;
    }
}
