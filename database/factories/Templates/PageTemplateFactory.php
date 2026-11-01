<?php

namespace Database\Factories\Templates;

use App\Models\Templates\PageTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageTemplate>
 */
class PageTemplateFactory extends Factory
{
    protected $model = PageTemplate::class;

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
            'content' => app(PageSchema::class)->normalize([]),
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
}
