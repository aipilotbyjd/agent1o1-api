<?php

namespace App\Console\Commands\Assistant;

use App\Services\Assistant\Briefings\BriefingScheduler;
use App\Services\Assistant\Meetings\MeetingPrepScheduler;
use Illuminate\Console\Command;

class RunDueBriefingsCommand extends Command
{
    protected $signature = 'assistant:run-due-briefings';

    protected $description = "Start every personal assistant report that is due in its owner's timezone.";

    public function handle(BriefingScheduler $daily, MeetingPrepScheduler $meetingPrep): int
    {
        $this->info("Started {$daily->startDue()} Daily report(s) and {$meetingPrep->startDue()} meeting brief(s).");

        return self::SUCCESS;
    }
}
