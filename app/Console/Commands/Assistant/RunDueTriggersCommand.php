<?php

namespace App\Console\Commands\Assistant;

use App\Services\Assistant\Triggers\TriggerScheduler;
use Illuminate\Console\Command;

class RunDueTriggersCommand extends Command
{
    protected $signature = 'assistant:run-due-triggers';

    protected $description = 'Fire every personal assistant schedule and one-time trigger that is due.';

    public function handle(TriggerScheduler $scheduler): int
    {
        $this->info("Fired {$scheduler->fireDue()} trigger(s).");

        return self::SUCCESS;
    }
}
