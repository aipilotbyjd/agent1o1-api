<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowPage>
 */
class WorkflowPageFactory extends Factory
{
    protected $model = WorkflowPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workflow::factory()->create()->workspace_id,
            'workflow_id' => fn (array $attributes) => Workflow::factory()->create(['workspace_id' => $attributes['workspace_id']])->id,
            'name' => fake()->words(2, true),
            'content' => app(PageSchema::class)->normalize([]),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }
}
