<?php

namespace Database\Factories\Notifications;

use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Every workspace event to a channel, unless a state narrows it.
 *
 * @extends Factory<NotificationRoute>
 */
class NotificationRouteFactory extends Factory
{
    protected $model = NotificationRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_channel_id' => NotificationChannel::factory(),
            'workspace_id' => fn (array $attributes) => NotificationChannel::query()->findOrFail($attributes['notification_channel_id'])->workspace_id,
            'event_key' => null,
            'category' => null,
            'min_severity' => null,
        ];
    }

    public function forChannel(NotificationChannel $channel): static
    {
        return $this->state(fn (): array => [
            'notification_channel_id' => $channel->id,
            'workspace_id' => $channel->workspace_id,
        ]);
    }
}
