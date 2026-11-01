<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\CatalogVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogVendor>
 */
class CatalogVendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(1),
            'label' => fake()->unique()->company(),
            'sort_order' => fake()->numberBetween(20, 900),
        ];
    }
}
