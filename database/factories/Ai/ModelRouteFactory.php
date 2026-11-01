<?php

namespace Database\Factories\Ai;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelRoute>
 */
class ModelRouteFactory extends Factory
{
    protected $model = ModelRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_catalog_id' => ModelCatalog::factory(),
            'execution_provider' => 'anthropic',
            'execution_model_id' => 'claude-3-5-sonnet-latest',
            'priority' => 0,
            'is_enabled' => true,
        ];
    }

    public function forCatalog(ModelCatalog $catalog): static
    {
        return $this->state(fn (): array => ['model_catalog_id' => $catalog->id]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
