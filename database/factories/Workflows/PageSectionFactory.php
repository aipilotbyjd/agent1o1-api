<?php

namespace Database\Factories\Workflows;

use App\Models\User;
use App\Models\Workflows\PageSection;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageSection>
 */
class PageSectionFactory extends Factory
{
    protected $model = PageSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'name' => fake()->words(2, true),
            'block' => app(PageSchema::class)->normalize(['blocks' => [['type' => 'section', 'children' => [['type' => 'heading', 'props' => ['text' => 'Hello']]]]]])['blocks'][0],
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }
}
