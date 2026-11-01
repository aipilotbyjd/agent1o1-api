<?php

namespace App\Services\Agents\Approvals;

use App\Models\Agents\AgentMessage;
use App\Services\Agents\AgentTurn;
use Carbon\CarbonInterface;
use Laravel\Ai\Approvals\Decisions;

/**
 * A paused turn ready to continue: the turn rebuilt around the message it
 * paused on, the decisions to hand the SDK, and the outcome of every call
 * settled before resuming (keyed by tool call id, in the stored
 * `tool_results` shape).
 */
final readonly class Resumption
{
    /**
     * @param  array<string, array<string, mixed>>  $settledResults
     */
    public function __construct(
        public AgentTurn $turn,
        public AgentMessage $message,
        public Decisions $decisions,
        public array $settledResults,
        public CarbonInterface $startedAt,
    ) {}
}
