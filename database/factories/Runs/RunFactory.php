<?php

namespace Database\Factories\Runs;

use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    protected $model = Run::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'runnable_type' => (new Workflow)->getMorphClass(),
            'runnable_id' => Workflow::factory(),
            'status' => RunStatus::Pending,
            'trigger_type' => 'manual',
            'input' => [],
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->current_version_id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
