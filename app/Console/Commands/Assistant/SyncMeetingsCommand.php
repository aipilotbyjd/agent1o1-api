<?php

namespace App\Console\Commands\Assistant;

use App\Services\Assistant\Meetings\MeetingPrepScheduler;
use Illuminate\Console\Command;

class SyncMeetingsCommand extends Command
{
    protected $signature = 'assistant:sync-meetings';

    protected $description = 'Sync upcoming meetings from the calendars of everyone with Meeting Prep on.';

    public function handle(MeetingPrepScheduler $scheduler): int
    {
        $this->info("Synced {$scheduler->syncAll()} calendar(s).");

        return self::SUCCESS;
    }
}
