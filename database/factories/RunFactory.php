<?php

namespace Database\Factories;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Models\Agent;
use App\Models\Run;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    protected $model = Run::class;

    /**
     * Default: a workflow execution.
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

    /**
     * An agent run instead of a workflow execution.
     */
    public function forAgent(?Agent $agent = null): static
    {
        return $this->state(function () use ($agent) {
            $agent ??= Agent::factory()->create();

            return [
                'workflow_id' => null,
                'mode' => null,
                'agent_id' => $agent->id,
                'workspace_id' => $agent->workspace_id,
                'source' => 'trigger',
                'status' => ExecutionStatus::Running,
            ];
        });
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
