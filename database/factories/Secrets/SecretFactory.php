<?php

namespace Database\Factories\Secrets;

use App\Models\Secrets\Secret;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    protected $model = Secret::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'key' => Str::upper(Str::snake(fake()->unique()->words(2, true))),
            'description' => fake()->sentence(),
            'value' => 'sk_test_'.fake()->uuid(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    /**
     * A readable entry: same row, same resolution, value not hidden.
     */
    public function variable(): static
    {
        return $this->state(fn (): array => [
            'is_secret' => false,
            'value' => fake()->url(),
        ]);
    }
}
