<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Models\Agents\KnowledgeSource;
use App\Services\Agents\Knowledge\KnowledgeSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Syncs one knowledge source. One at a time per source.
 */
class SyncKnowledgeSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public KnowledgeSource $source)
    {
        $this->onConnection(config('queue.long_running_connection'));
        $this->onQueue(Queue::LongRunning->value);
    }

    public function uniqueId(): string
    {
        return $this->source->id;
    }

    public function handle(KnowledgeSync $sync): void
    {
        $sync->sync($this->source);
    }
}
