<?php

namespace Database\Factories\Notifications;

use App\Enums\Notifications\DeliveryStatus;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_channel_id' => NotificationChannel::factory(),
            'workspace_id' => fn (array $attributes) => NotificationChannel::query()->find($attributes['notification_channel_id'])?->workspace_id,
            'event_key' => 'run.failed',
            'channel' => 'webhook',
            'target' => fake()->words(2, true),
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'payload' => ['message' => 'Hello'],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => ['status' => DeliveryStatus::Sent, 'attempts' => 1, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => DeliveryStatus::Failed, 'attempts' => 4, 'error' => 'Delivery failed: HTTP 500.']);
    }
}
