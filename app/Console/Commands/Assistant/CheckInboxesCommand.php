<?php

namespace App\Console\Commands\Assistant;

use App\Jobs\Assistant\CheckInboxJob;
use App\Models\Assistant\AssistantInboxConfig;
use Illuminate\Console\Command;

class CheckInboxesCommand extends Command
{
    protected $signature = 'assistant:check-inboxes';

    protected $description = 'Queue a Smart Inbox check for every enabled mailbox.';

    public function handle(): int
    {
        $count = 0;

        AssistantInboxConfig::query()->where('enabled', true)->each(function (AssistantInboxConfig $config) use (&$count): void {
            CheckInboxJob::dispatch($config);
            $count++;
        });

        $this->info("Queued {$count} inbox check(s).");

        return self::SUCCESS;
    }
}
