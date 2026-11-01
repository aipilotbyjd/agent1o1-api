<?php

namespace Database\Factories\Agents;

use App\Models\Agents\SkillSource;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillSource>
 */
class SkillSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'repo' => 'acme/skills',
            'branch' => 'main',
            'path' => null,
            'is_shared' => true,
        ];
    }
}
