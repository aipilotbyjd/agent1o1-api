<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Models\Agents\Agent;
use App\Services\Agents\ReflectionAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one scheduled reflection pass for an agent: dispatched by
 * `Console\Commands\Agents\RunDueReflectionsCommand`.
 */
class RunReflectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public Agent $agent)
    {
        $this->onConnection(config('queue.long_running_connection'));
        $this->onQueue(Queue::LongRunning->value);
    }

    public function handle(ReflectionAnalyzer $analyzer): void
    {
        $analyzer->run($this->agent);
    }
}
