<?php

namespace Database\Factories;

use App\Models\PinnedNodeData;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PinnedNodeData>
 */
class PinnedNodeDataFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'node_id' => fake()->uuid(),
            'data' => ['sample' => fake()->word()],
        ];
    }
}
