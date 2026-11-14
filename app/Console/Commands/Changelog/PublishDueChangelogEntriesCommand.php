<?php

namespace App\Console\Commands\Changelog;

use App\Services\Changelog\ChangelogService;
use Illuminate\Console\Command;

/**
 * Run every minute: publishes scheduled changelog entries whose time has come.
 */
class PublishDueChangelogEntriesCommand extends Command
{
    protected $signature = 'changelog:publish-due';

    protected $description = 'Publishes scheduled changelog entries whose time has come.';

    public function handle(ChangelogService $changelog): int
    {
        $published = $changelog->publishDue();

        $this->info("Published {$published} changelog entr".($published === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
