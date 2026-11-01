<?php

namespace Database\Factories\Notifications;

use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

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
            'user_id' => User::factory(),
            'event_key' => 'workspace.member_invited',
            'in_app' => true,
            'email' => false,
            'channel_ids' => null,
        ];
    }
}
