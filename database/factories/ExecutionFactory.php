<?php

namespace Database\Factories;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Models\Execution;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Execution>
 */
class ExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workspace_id' => Workspace::factory(),
            'status' => ExecutionStatus::Pending,
            'mode' => ExecutionMode::Manual,
            'trigger_data' => [],
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => ExecutionStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ExecutionStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration_ms' => 1200,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ExecutionStatus::Failed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => ['message' => 'Something went wrong'],
        ]);
    }
}
