<?php

namespace App\Events\Notifications;

use App\Broadcasting\BroadcastsReliably;
use App\Http\Resources\Api\Internal\V1\Notifications\NotificationResource;
use App\Models\Notifications\InboxNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A notification landed in a member's inbox, or a grouped one repeated
 * (`grouped`). Carries the same shape `GET /notifications` returns, so an
 * open app can put it straight into its list without refetching.
 *
 * Sent on the member's own private channel (`App.Models.User.{id}`).
 * Wire name: `inbox.notification`.
 */
class InboxNotificationChanged implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Model $notifiable,
        public readonly InboxNotification $notification,
        public readonly bool $grouped,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(str_replace('\\', '.', $this->notifiable::class).'.'.$this->notifiable->getKey())];
    }

    public function broadcastAs(): string
    {
        return 'inbox.notification';
    }

    /**
     * @return array{notification: array<string, mixed>, grouped: bool, emitted_at: int}
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => NotificationResource::make($this->notification)->resolve(),
            'grouped' => $this->grouped,
            'emitted_at' => $this->emittedAt(),
        ];
    }
}
