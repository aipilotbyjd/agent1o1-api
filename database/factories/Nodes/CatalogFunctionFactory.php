<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\CatalogFunction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogFunction>
 */
class CatalogFunctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'label' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(20, 900),
            'is_system' => false,
        ];
    }
}
