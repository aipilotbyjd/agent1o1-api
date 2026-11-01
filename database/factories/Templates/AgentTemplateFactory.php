<?php

namespace Database\Factories\Templates;

use App\Models\Templates\AgentTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentTemplate>
 */
class AgentTemplateFactory extends Factory
{
    protected $model = AgentTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'config' => [
                'instructions' => 'You are a helpful assistant.',
                'provider' => 'anthropic',
            ],
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
