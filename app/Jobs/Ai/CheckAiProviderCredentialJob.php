<?php

namespace App\Jobs\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Queue;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderCredentialAlerts;
use App\Services\Ai\AiProviderKeyChecker;
use App\Services\Ai\Connect\AiProviderTokenRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background re-check of a workspace's own AI provider key. A rejected key
 * is not something the SDK fails over from, so this is what takes a revoked
 * key out of rotation before it keeps failing calls. Whoever relies on it
 * is told the moment a working key stops working (`AiProviderCredentialAlerts`).
 * A one-click connection whose token has run out is renewed first, so an
 * expired token is not mistaken for a revoked one.
 */
class CheckAiProviderCredentialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public AiProviderCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(AiProviderKeyChecker $checker, AiProviderTokenRefresher $refresher, AiProviderCredentialAlerts $alerts): void
    {
        $wasValid = $this->credential->isValid();

        if ($this->credential->tokenExpiresWithin(60) && ! $refresher->refresh($this->credential)) {
            if ($wasValid) {
                $alerts->stoppedWorking($this->credential);
            }

            return;
        }

        $checker->validate($this->credential);

        if (! $wasValid || $this->credential->validation_status !== AiProviderCredentialStatus::Invalid) {
            return;
        }

        $alerts->stoppedWorking($this->credential);
    }
}
