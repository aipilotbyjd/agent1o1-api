<?php

namespace Database\Factories\Ai;

use App\Models\Ai\ModelCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModelCatalog>
 */
class ModelCatalogFactory extends Factory
{
    protected $model = ModelCatalog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'display_name' => ucwords($name),
            'brand' => fake()->randomElement(['anthropic', 'openai', 'meta', 'google']),
            'capabilities' => ['context_window' => 128000],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
