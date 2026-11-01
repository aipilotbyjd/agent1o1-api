<?php

namespace Database\Factories\Notifications;

use App\Models\Notifications\NotificationChannel;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'created_by' => User::factory(),
            'type' => 'webhook',
            'name' => fake()->words(2, true),
            'config' => ['url' => fake()->url()],
            'is_active' => true,
        ];
    }
}
