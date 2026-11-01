<?php

namespace Database\Factories\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\TriggerPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriggerPreset>
 */
class TriggerPresetFactory extends Factory
{
    protected $model = TriggerPreset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'schedule',
            'key' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'type' => TriggerType::Schedule,
            'config' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function github(): static
    {
        return $this->state(fn (): array => [
            'category' => 'github',
            'key' => 'github.push',
            'name' => 'GitHub: On Push',
            'type' => TriggerType::Webhook,
            'signature_scheme' => 'github',
            'dedupe_header' => 'X-GitHub-Delivery',
        ]);
    }
}
