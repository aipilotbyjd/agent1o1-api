<?php

namespace App\Console\Commands\Connectors;

use App\Jobs\Connectors\CheckConnectorCredentialHealthJob;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Console\Command;

class CheckConnectorHealthCommand extends Command
{
    protected $signature = 'connectors:check-health {--hours=24 : Re-check connections not checked within this many hours}';

    protected $description = 'Queue a provider check for every connection that has not been checked recently.';

    /**
     * Skips connections already known to be expired: they can't pass until
     * someone reconnects them, and the admins have been told.
     */
    public function handle(): int
    {
        $count = 0;
        $staleBefore = now()->subHours((int) $this->option('hours'));

        ConnectorCredential::query()
            ->with('connector')
            ->where(fn ($query) => $query->whereNull('last_tested_at')->orWhere('last_tested_at', '<', $staleBefore))
            ->each(function (ConnectorCredential $credential) use (&$count): void {
                if (! $credential->isUsable()) {
                    return;
                }

                CheckConnectorCredentialHealthJob::dispatch($credential);
                $count++;
            });

        $this->info("Queued {$count} connection check(s).");

        return self::SUCCESS;
    }
}
