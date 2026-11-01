<?php

namespace Database\Factories\Workspaces;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceDataEntry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceDataEntry>
 */
class WorkspaceDataEntryFactory extends Factory
{
    protected $model = WorkspaceDataEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)->create(User::factory()->create(), ['name' => fake()->company()])->id,
            'store' => 'default',
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'expires_at' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
