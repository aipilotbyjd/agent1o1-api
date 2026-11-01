<?php

namespace App\Console\Commands\Connectors;

use App\Jobs\Connectors\RefreshConnectorCredentialJob;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Console\Command;

class RefreshExpiringConnectorsCommand extends Command
{
    protected $signature = 'connectors:refresh-expiring {--minutes=10 : Refresh tokens expiring within this many minutes}';

    protected $description = 'Queue a token refresh for every OAuth connection about to expire.';

    /**
     * Only tokens that haven't expired yet: a failed refresh marks its
     * credential expired (and notifies the admins once), so it isn't
     * retried here every few minutes.
     */
    public function handle(): int
    {
        $count = 0;

        ConnectorCredential::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addMinutes((int) $this->option('minutes')))
            ->each(function (ConnectorCredential $credential) use (&$count): void {
                if (blank($credential->data['refresh_token'] ?? null)) {
                    return;
                }

                RefreshConnectorCredentialJob::dispatch($credential);
                $count++;
            });

        $this->info("Queued {$count} connector refresh(es).");

        return self::SUCCESS;
    }
}
