<?php

namespace Database\Factories\Workflows\Builder;

use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderSession>
 */
class WorkflowBuilderSessionFactory extends Factory
{
    protected $model = WorkflowBuilderSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = User::factory()->create();

        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create($owner, ['name' => fake()->company()])
                ->id,
            'user_id' => $owner->id,
            'draft_graph' => ['nodes' => [], 'edges' => []],
        ];
    }

    public function forWorkspace(Workspace $workspace, User $user): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    }
}
