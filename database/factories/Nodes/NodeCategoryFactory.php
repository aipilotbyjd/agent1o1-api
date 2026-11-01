<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\NodeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NodeCategory>
 */
class NodeCategoryFactory extends Factory
{
    protected $model = NodeCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'box',
            'color' => '#6366f1',
            'sort_order' => fake()->numberBetween(1, 20),
            'kind' => 'app',
        ];
    }
}
