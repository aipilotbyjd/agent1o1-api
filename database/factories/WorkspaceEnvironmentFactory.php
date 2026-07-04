<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceEnvironment>
 */
class WorkspaceEnvironmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Development', 'Staging', 'Production']).' '.Str::random(4);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'variables' => [],
            'is_default' => false,
        ];
    }
}
