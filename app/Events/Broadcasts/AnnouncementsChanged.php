<?php

namespace App\Events\Broadcasts;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An announcement went live, changed or ended, so open apps should refetch
 * `GET /announcements`. Deliberately content-free: whether a given member sees
 * a banner is decided by their own request, not by what was pushed.
 *
 * Wire name: `announcements.changed`.
 */
class AnnouncementsChanged implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $reason = 'changed') {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::ANNOUNCEMENTS)];
    }

    public function broadcastAs(): string
    {
        return 'announcements.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason];
    }
}
