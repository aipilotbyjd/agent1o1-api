<?php

namespace App\Console\Commands;

use App\Services\WorkflowBuilder\SessionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflow-builder:cleanup {--days=30 : Archive sessions idle for this many days}')]
#[Description('Archive idle AI workflow builder sessions')]
class CleanupBuilderSessionsCommand extends Command
{
    public function handle(SessionService $sessionService): int
    {
        $days = (int) $this->option('days');
        $count = $sessionService->cleanupIdle($days);

        $this->info("Archived {$count} idle workflow builder sessions (idle > {$days} days).");

        return Command::SUCCESS;
    }
}
