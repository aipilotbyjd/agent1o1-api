<?php

namespace Database\Factories\Templates;

use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    protected $model = WorkflowTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'graph' => ['nodes' => [], 'edges' => []],
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function global(): static
    {
        return $this->state(fn (): array => ['workspace_id' => null, 'visibility' => 'public']);
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['visibility' => 'public']);
    }
}
