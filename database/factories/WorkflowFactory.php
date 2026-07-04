<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'is_active' => false,
            'is_locked' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }

    /**
     * Attach a published version with a minimal trigger → transform graph.
     */
    public function withSimpleGraph(): static
    {
        return $this->afterCreating(function (Workflow $workflow) {
            $version = $workflow->versions()->create([
                'workspace_id' => $workflow->workspace_id,
                'version_number' => 1,
                'nodes_data' => [
                    ['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'Start', 'config' => []],
                    ['id' => 'transform-1', 'type' => 'transform', 'name' => 'Transform', 'config' => ['output' => ['greeting' => 'hello']]],
                ],
                'edges_data' => [
                    ['source' => 'trigger-1', 'target' => 'transform-1'],
                ],
            ]);

            $workflow->update(['current_version_id' => $version->id]);
        });
    }
}
