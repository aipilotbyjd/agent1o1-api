<?php

namespace App\Services;

use App\Models\ConnectorMetric;
use Illuminate\Support\Facades\DB;

class ConnectorMetricService
{
    /**
     * Record a single connector call into the daily rollup.
     */
    public function record(string $workspaceId, string $connector, bool $success, int $durationMs): void
    {
        $date = now()->toDateString();

        // Ensure the daily row exists, then increment atomically via the query
        // builder. Incrementing through an Eloquent model instead would assign the
        // raw SQL Expression into the integer-cast columns and blow up on save.
        ConnectorMetric::firstOrCreate(
            ['workspace_id' => $workspaceId, 'connector' => $connector, 'date' => $date],
        );

        ConnectorMetric::where('workspace_id', $workspaceId)
            ->where('connector', $connector)
            ->where('date', $date)
            ->update([
                'total_calls' => DB::raw('total_calls + 1'),
                'success_calls' => DB::raw('success_calls + '.(int) $success),
                'failed_calls' => DB::raw('failed_calls + '.(int) ! $success),
                'total_duration_ms' => DB::raw('total_duration_ms + '.max(0, (int) $durationMs)),
            ]);
    }
}
