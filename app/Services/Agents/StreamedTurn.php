<?php

namespace App\Services\Agents;

use App\Models\Runs\Run;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * A turn that has been opened but not yet delivered: the `Run` it is being
 * recorded against, and the provider stream to iterate.
 *
 * Nothing has been sent to the provider until `$response` is iterated, which
 * is why the caller (not `AgentRunner`) is the one that must report a
 * failure (`AgentRunner::failTurn()`).
 */
final readonly class StreamedTurn
{
    public function __construct(
        public Run $run,
        public StreamableAgentResponse $response,
    ) {}
}
