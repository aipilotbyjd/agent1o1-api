<?php

namespace App\Console\Commands\Assistant;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Jobs\Assistant\CheckFollowUpJob;
use App\Models\Assistant\AssistantFollowUp;
use Illuminate\Console\Command;

class CheckFollowUpsCommand extends Command
{
    protected $signature = 'assistant:check-follow-ups';

    protected $description = 'Queue a check for every email follow-up whose reminder date has passed.';

    public function handle(): int
    {
        $count = 0;

        AssistantFollowUp::query()
            ->where('status', AssistantFollowUpStatus::Pending)
            ->where('remind_at', '<=', now())
            ->each(function (AssistantFollowUp $followUp) use (&$count): void {
                CheckFollowUpJob::dispatch($followUp);
                $count++;
            });

        $this->info("Queued {$count} follow-up check(s).");

        return self::SUCCESS;
    }
}
