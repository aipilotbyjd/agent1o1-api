<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Contracts\Suspendable;
use App\Engine\ExecutionPause;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Carbon\Carbon;
use Illuminate\Support\Str;

class WaitNode implements NodeHandler, Suspendable
{
    public function handle(NodeInput $input): NodeResult
    {
        // Resumed from webhook wait — return whatever was posted to the wait endpoint
        $resumeData = $input->config['resume_data'] ?? $input->inputData;

        return NodeResult::completed(is_array($resumeData) ? $resumeData : ['data' => $resumeData]);
    }

    public function pause(NodeInput $input): ExecutionPause
    {
        $timeoutMinutes = (int) ($input->config['timeout_minutes'] ?? 60 * 24); // 24h default
        $resumeAt = Carbon::now()->addMinutes($timeoutMinutes);
        $uuid = Str::uuid()->toString();

        return ExecutionPause::forWebhookWait($resumeAt, $uuid);
    }
}
