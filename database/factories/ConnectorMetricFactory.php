<?php

namespace Database\Factories;

use App\Models\ConnectorMetric;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectorMetric>
 */
class ConnectorMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'connector' => 'slack',
            'date' => now()->toDateString(),
            'total_calls' => 10,
            'success_calls' => 8,
            'failed_calls' => 2,
            'total_duration_ms' => 5000,
        ];
    }
}
