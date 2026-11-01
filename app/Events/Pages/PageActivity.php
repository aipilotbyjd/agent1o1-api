<?php

namespace App\Events\Pages;

use App\Broadcasting\Channels;
use App\Models\Workflows\WorkflowPage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed on a page that everyone building it should see without
 * reloading. Carries ids and states only: the builder refetches.
 *
 * Event names on the wire (`page.<type>`):
 * - `page.comments`: a comment was added, edited, resolved or deleted.
 * - `page.generation`: an AI generation finished (`id`, `status`).
 */
class PageActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly WorkflowPage $page,
        public readonly string $type,
        public readonly array $payload = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::page($this->page))];
    }

    public function broadcastAs(): string
    {
        return "page.{$this->type}";
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
