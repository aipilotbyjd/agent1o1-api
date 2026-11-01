<?php

namespace App\Jobs\Agents;

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

    public function __construct(public Agent $agent) {}

    public function handle(ReflectionAnalyzer $analyzer): void
    {
        $analyzer->run($this->agent);
    }
}
