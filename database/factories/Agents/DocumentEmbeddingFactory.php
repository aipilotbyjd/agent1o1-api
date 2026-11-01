<?php

namespace Database\Factories\Agents;

use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentEmbedding>
 */
class DocumentEmbeddingFactory extends Factory
{
    protected $model = DocumentEmbedding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'collection' => 'default',
            'source' => $this->faker->unique()->slug().'.md',
            'chunk_index' => 0,
            'chunk_text' => $this->faker->paragraph(),
            'embedding' => [1.0, 0.0],
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(['workspace_id' => $workspace->id]);
    }
}
