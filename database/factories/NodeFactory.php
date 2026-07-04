<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\NodeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => NodeCategory::factory(),
            'workspace_id' => null,
            'type' => 'node_'.fake()->unique()->numerify('######'),
            'version' => 1,
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'icon' => 'cube',
            'color' => '#6366f1',
            'node_kind' => 'action',
            'config_schema' => [],
            'input_schema' => [],
            'output_schema' => [],
            'is_active' => true,
            'is_premium' => false,
            'is_custom' => false,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn () => ['is_premium' => true]);
    }
}
