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

        $metric = ConnectorMetric::firstOrCreate(
            ['workspace_id' => $workspaceId, 'connector' => $connector, 'date' => $date],
        );

        $metric->forceFill([
            'total_calls' => DB::raw('total_calls + 1'),
            'success_calls' => DB::raw('success_calls + '.(int) $success),
            'failed_calls' => DB::raw('failed_calls + '.(int) ! $success),
            'total_duration_ms' => DB::raw('total_duration_ms + '.max(0, (int) $durationMs)),
        ])->save();
    }
}
