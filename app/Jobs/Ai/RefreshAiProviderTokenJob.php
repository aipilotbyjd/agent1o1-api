<?php

namespace App\Jobs\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Queue;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderCredentialAlerts;
use App\Services\Ai\Connect\AiProviderTokenRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renews a one-click connection's OAuth token before it expires, and tells
 * whoever relies on it when it can't be renewed and needs reconnecting.
 */
class RefreshAiProviderTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public AiProviderCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(AiProviderTokenRefresher $refresher, AiProviderCredentialAlerts $alerts): void
    {
        $wasValid = $this->credential->isValid();

        if (! $refresher->refresh($this->credential) && $wasValid && $this->credential->validation_status === AiProviderCredentialStatus::Invalid) {
            $alerts->stoppedWorking($this->credential);
        }
    }
}
