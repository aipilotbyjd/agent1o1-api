<?php

namespace Database\Factories\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertEvent>
 */
class AlertEventFactory extends Factory
{
    protected $model = AlertEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_rule_id' => AlertRule::factory(),
            'fingerprint' => fake()->unique()->slug(2),
            'severity' => AlertSeverity::Warning,
            'status' => AlertEvent::STATUS_OPEN,
            'title' => fake()->sentence(),
            'context' => [],
            'first_fired_at' => now(),
            'last_fired_at' => now(),
            'occurrences' => 1,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['status' => AlertEvent::STATUS_RESOLVED, 'resolved_at' => now()]);
    }
}
