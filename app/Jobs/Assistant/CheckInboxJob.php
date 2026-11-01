<?php

namespace App\Jobs\Assistant;

use App\Models\Assistant\AssistantInboxConfig;
use App\Services\Assistant\Inbox\InboxAccess;
use App\Services\Assistant\Inbox\InboxProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One Smart Inbox check for one mailbox. Unique per mailbox, so a slow
 * check is never overlapped by the next tick.
 */
class CheckInboxJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor = 900;

    public function __construct(public AssistantInboxConfig $config)
    {
        $this->timeout = (int) config('assistant.runtime.job_timeout');
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function uniqueId(): string
    {
        return $this->config->id;
    }

    public function handle(InboxProcessor $processor, InboxAccess $access): void
    {
        $config = $this->config->refresh();

        // Smart Inbox turns itself off when the plan or the owner's access no
        // longer allows it; turning it back on is the owner's choice.
        if (! $access->allows($config->assistant)) {
            $access->switchOff($config);

            return;
        }

        $processor->check($config);
    }
}
