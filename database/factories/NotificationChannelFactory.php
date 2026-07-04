<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => 'slack',
            'name' => fake()->words(2, true),
            'config' => ['url' => 'https://hooks.slack.test/'.fake()->uuid()],
            'is_active' => true,
        ];
    }
}
