<?php

namespace Database\Factories;

use App\Models\AgentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentTemplate>
 */
class AgentTemplateFactory extends Factory
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
            'category' => fake()->randomElement(['support', 'sales', 'productivity', 'research']),
            'icon' => null,
            'color' => null,
            'tags' => [],
            'system_prompt' => fake()->paragraph(),
            'llm_provider' => 'anthropic',
            'llm_model' => 'claude-sonnet-4-6',
            'llm_settings' => ['max_steps' => 15, 'timeout_seconds' => 180],
            'tool_configs' => [],
            'example_conversations' => [],
            'instructions' => fake()->sentence(),
            'is_featured' => false,
            'is_active' => true,
            'usage_count' => 0,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
