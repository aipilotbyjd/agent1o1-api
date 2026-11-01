<?php

namespace App\Jobs\Connectors;

use App\Enums\Queue;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Notifications\Connectors\ConnectorCredentialExpiredNotification;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Refreshes an OAuth `ConnectorCredential` before/after its access token
 * expires. On final failure (all retries exhausted), the credential is
 * marked expired and the workspace's owners/admins are notified so a human
 * can reconnect it: mirrors the old project's `RefreshOAuthTokenJob`.
 */
class RefreshConnectorCredentialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public int $tries = 3;

    public function __construct(public ConnectorCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(ConnectorTokens $tokens): void
    {
        $refreshed = $tokens->refresh($this->credential, force: true);

        // The provider rejected the refresh token: retrying won't help, so
        // fail now and let `failed()` tell the admins to reconnect.
        if (filled($refreshed->data['refresh_token'] ?? null) && ! $refreshed->canRefresh()) {
            $this->fail(ConnectorException::needsReconnect($refreshed));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->credential->update(['expires_at' => now()]);
        app(ConnectorTokens::class)->markRejected($this->credential->refresh());

        app(NotificationDispatcher::class)->dispatch(
            app(NotificationDispatcher::class)->ownersAndAdmins($this->credential->workspace),
            new ConnectorCredentialExpiredNotification($this->credential),
        );
    }
}
