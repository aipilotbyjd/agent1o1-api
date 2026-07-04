<?php

namespace App\Jobs;

use App\Models\Credential;
use App\Notifications\CredentialExpiredNotification;
use App\Services\OAuthCredentialFlowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshOAuthTokenJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $credentialId) {}

    public function handle(OAuthCredentialFlowService $flow): void
    {
        $credential = Credential::find($this->credentialId);

        if ($credential) {
            $flow->refresh($credential);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $credential = Credential::with('workspace.owner')->find($this->credentialId);

        if (! $credential) {
            return;
        }

        $credential->update(['expires_at' => now()]);

        $credential->workspace->owner?->notify(new CredentialExpiredNotification($credential));
    }
}
