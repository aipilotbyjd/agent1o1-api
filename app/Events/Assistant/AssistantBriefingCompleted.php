<?php

namespace App\Events\Assistant;

use App\Broadcasting\Channels;
use App\Models\Assistant\AssistantBriefingRun;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A report finished (or failed): the owner's open home screen refreshes
 * its Daily and Situations tabs. Sent on the assistant's owner-only channel.
 */
class AssistantBriefingCompleted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AssistantBriefingRun $run) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::assistant($this->run->config->assistant));
    }

    public function broadcastAs(): string
    {
        return 'assistant.briefing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['run_id' => $this->run->id, 'status' => $this->run->status->value];
    }
}
