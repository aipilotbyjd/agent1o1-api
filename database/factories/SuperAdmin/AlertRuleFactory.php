<?php

namespace Database\Factories\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    protected $model = AlertRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'type' => 'queue_backlog',
            'config' => [],
            'severity' => AlertSeverity::Warning,
            'cooldown_minutes' => 60,
            'enabled' => true,
            'snoozed_until' => null,
        ];
    }
}
