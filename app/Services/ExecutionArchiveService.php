<?php

namespace App\Services;

use App\Enums\ExecutionStatus;
use App\Models\ArchivedExecutionLog;
use App\Models\ExecutionLog;
use App\Models\Run;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExecutionArchiveService
{
    /**
     * Move execution logs older than the cutoff into cold storage.
     *
     * @return int Number of logs archived.
     */
    public function archiveOlderThan(int $days): int
    {
        $cutoff = CarbonImmutable::now()->subDays($days);
        $archived = 0;

        ExecutionLog::query()
            ->where('logged_at', '<', $cutoff)
            ->chunkById(500, function ($logs) use (&$archived) {
                $rows = $logs->map(fn (ExecutionLog $log) => [
                    'id' => $log->id,
                    'execution_id' => $log->execution_id,
                    'workspace_id' => $log->workspace_id,
                    'node_id' => $log->node_id,
                    'level' => $log->level,
                    'message' => $log->message,
                    'context' => $log->getRawOriginal('context'),
                    'logged_at' => $log->logged_at,
                    'archived_at' => now(),
                ])->all();

                DB::transaction(function () use ($rows, $logs) {
                    ArchivedExecutionLog::insert($rows);
                    ExecutionLog::whereIn('id', $logs->pluck('id'))->delete();
                });

                $archived += count($rows);
            });

        return $archived;
    }

    /**
     * Delete terminal executions older than the cutoff.
     *
     * @return int Number of executions pruned.
     */
    public function pruneOlderThan(int $days): int
    {
        $cutoff = CarbonImmutable::now()->subDays($days);

        return Run::query()
            ->whereIn('status', [
                ExecutionStatus::Completed,
                ExecutionStatus::Failed,
                ExecutionStatus::Cancelled,
            ])
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
