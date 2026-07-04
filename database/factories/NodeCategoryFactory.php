<?php

namespace Database\Factories;

use App\Models\NodeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NodeCategory>
 */
class NodeCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->optional()->sentence(),
            'icon' => 'cube',
            'color' => '#6366f1',
            'sort_order' => 0,
            'kind' => 'core',
        ];
    }
}
