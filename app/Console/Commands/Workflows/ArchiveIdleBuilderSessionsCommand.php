<?php

namespace App\Console\Commands\Workflows;

use App\Enums\Workflows\BuilderSessionStatus;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Console\Command;

/**
 * Archives builder sessions nobody has touched in a while, so the session
 * list stays about current work. Only sessions that never produced a
 * workflow are archived: a promoted one is the history behind a real
 * workflow. Nothing is deleted: an archived session can be reopened by
 * setting its status back to `active`.
 */
class ArchiveIdleBuilderSessionsCommand extends Command
{
    protected $signature = 'workflow-builder:archive-idle {--days=30 : Archive sessions idle for at least this many days}';

    protected $description = 'Archive workflow builder sessions that have been idle and never promoted.';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));

        $archived = WorkflowBuilderSession::query()
            ->where('status', BuilderSessionStatus::Active)
            ->whereNull('workflow_id')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_activity_at', '<', $cutoff)
                    ->orWhere(fn ($query) => $query->whereNull('last_activity_at')->where('created_at', '<', $cutoff));
            })
            ->update(['status' => BuilderSessionStatus::Archived, 'updated_at' => now()]);

        $this->info("Archived {$archived} idle workflow builder session(s).");

        return self::SUCCESS;
    }
}
