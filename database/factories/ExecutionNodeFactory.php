<?php

namespace Database\Factories;

use App\Enums\ExecutionNodeStatus;
use App\Models\ExecutionNode;
use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutionNode>
 */
class ExecutionNodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'execution_id' => Run::factory(),
            'node_id' => fake()->uuid(),
            'node_run_key' => fake()->uuid(),
            'node_type' => 'transform',
            'node_name' => fake()->words(2, true),
            'status' => ExecutionNodeStatus::Completed,
            'started_at' => now()->subSeconds(2),
            'finished_at' => now(),
            'duration_ms' => fake()->numberBetween(10, 500),
            'input_data' => [],
            'output_data' => null,
            'error' => null,
            'sequence' => 1,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ExecutionNodeStatus::Completed,
            'output_data' => ['result' => fake()->word()],
            'error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ExecutionNodeStatus::Failed,
            'output_data' => null,
            'error' => ['message' => fake()->sentence()],
        ]);
    }
}
