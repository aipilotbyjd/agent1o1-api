<?php

namespace Database\Factories;

use App\Enums\BuilderSessionStatus;
use App\Models\User;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderSession>
 */
class WorkflowBuilderSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'nodes_draft' => [],
            'edges_draft' => [],
            'draft_lock_version' => 0,
            'status' => BuilderSessionStatus::Active,
            'last_activity_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => BuilderSessionStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(['status' => BuilderSessionStatus::Archived]);
    }

    public function withNodes(array $nodes, array $edges = []): static
    {
        return $this->state(['nodes_draft' => $nodes, 'edges_draft' => $edges]);
    }
}
