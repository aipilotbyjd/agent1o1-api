<?php

namespace Database\Factories;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['marketing', 'sales', 'ops', 'dev']),
            'tags' => [],
            'nodes_data' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'Start'],
                ['id' => 'transform-1', 'type' => 'transform', 'name' => 'Out', 'config' => ['output' => ['ok' => true]]],
            ],
            'edges_data' => [['source' => 'trigger-1', 'target' => 'transform-1']],
            'is_featured' => false,
            'is_active' => true,
            'usage_count' => 0,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
