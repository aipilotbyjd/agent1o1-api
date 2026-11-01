<?php

namespace Database\Factories\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'type' => TriggerTargetType::Workflow->value,
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->safeHexColor(),
            'position' => 0,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function forAgents(): static
    {
        return $this->state(fn (): array => ['type' => TriggerTargetType::Agent->value]);
    }

    public function forParent(Folder $parent): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $parent->workspace_id,
            'type' => $parent->type->value,
            'parent_id' => $parent->id,
        ]);
    }
}
