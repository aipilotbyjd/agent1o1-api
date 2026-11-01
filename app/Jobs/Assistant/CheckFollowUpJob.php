<?php

namespace App\Jobs\Assistant;

use App\Models\Assistant\AssistantFollowUp;
use App\Services\Assistant\Inbox\FollowUpChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks one due follow-up. Unique per follow-up, so a slow mailbox is
 * never checked twice at once.
 */
class CheckFollowUpJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public AssistantFollowUp $followUp)
    {
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function uniqueId(): string
    {
        return $this->followUp->id;
    }

    public function handle(FollowUpChecker $checker): void
    {
        $checker->check($this->followUp);
    }
}
