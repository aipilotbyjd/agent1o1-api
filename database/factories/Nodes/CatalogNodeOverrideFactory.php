<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\CatalogNodeOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogNodeOverride>
 */
class CatalogNodeOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'node_type' => fake()->unique()->slug(2, false).'_node',
            'status' => fake()->randomElement(['beta', 'deprecated', 'hidden']),
            'note' => null,
        ];
    }
}
