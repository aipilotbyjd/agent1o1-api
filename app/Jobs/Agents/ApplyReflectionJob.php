<?php

namespace App\Jobs\Agents;

use App\Models\Agents\Reflection;
use App\Services\Agents\ReflectionApplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Applies one auto-eligible `Reflection`: dispatched by
 * `Services\Agents\ReflectionAnalyzer` when `apply_behavior` is
 * `auto_apply` and the proposal clears the confidence/support bar. A
 * human-triggered "Apply" click goes through `ReflectionApplier` directly
 * instead, so its result is available synchronously to the request.
 */
class ApplyReflectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Reflection $reflection) {}

    public function handle(ReflectionApplier $applier): void
    {
        $applier->apply($this->reflection);
    }
}
