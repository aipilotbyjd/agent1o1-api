<?php

namespace Database\Factories\Assistant;

use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assistant>
 */
class AssistantFactory extends Factory
{
    protected $model = Assistant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => fn (array $attributes) => app(WorkspaceService::class)
                ->create(User::findOrFail($attributes['user_id']), ['name' => fake()->company()])
                ->id,
        ];
    }

    public function forMember(Workspace $workspace, User $user): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
    }
}
