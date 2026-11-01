<?php

namespace App\Jobs\Assistant;

use App\Services\Assistant\Channels\SlackChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Slack wants an answer to an event within three seconds, so the work
 * (looking up the sender, starting the turn) happens here.
 */
class HandleSlackMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public string $teamId, public array $event)
    {
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function handle(SlackChannel $slack): void
    {
        $slack->handle($this->teamId, $this->event);
    }
}
