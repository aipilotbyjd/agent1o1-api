<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Contracts\Suspendable;
use App\Engine\ExecutionPause;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Carbon\Carbon;

class DelayNode implements NodeHandler, Suspendable
{
    public function handle(NodeInput $input): NodeResult
    {
        // If we get here, the delay has already elapsed (resumed from checkpoint)
        return NodeResult::completed(['delayed' => true, 'resumed_at' => now()->toISOString()]);
    }

    public function pause(NodeInput $input): ExecutionPause
    {
        $amount = (int) ($input->config['amount'] ?? 1);
        $unit = $input->config['unit'] ?? 'minutes'; // seconds|minutes|hours|days

        $resumeAt = match ($unit) {
            'seconds' => Carbon::now()->addSeconds($amount),
            'minutes' => Carbon::now()->addMinutes($amount),
            'hours' => Carbon::now()->addHours($amount),
            'days' => Carbon::now()->addDays($amount),
            default => Carbon::now()->addMinutes($amount),
        };

        return ExecutionPause::forDelay($resumeAt, [
            'delay_amount' => $amount,
            'delay_unit' => $unit,
            'paused_at' => now()->toISOString(),
        ]);
    }
}
