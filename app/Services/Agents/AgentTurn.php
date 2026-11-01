<?php

namespace App\Services\Agents;

use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

/**
 * Everything one chat turn needs after its setup and before its result:
 * carried between `AgentRunner`'s `openTurn()` and `completeTurn()` so the
 * synchronous and streaming paths share exactly one definition of what a
 * turn is.
 *
 * `provider`/`model` are captured here rather than re-read from the agent
 * later: they come from the session's *pinned* version, and re-reading them
 * at completion time would reintroduce the drift pinning exists to prevent.
 *
 * `provider` is either a plain provider name (agent has no `model_catalog_id`)
 * or the ordered `provider => model` failover chain resolved from one by
 * `Services\Ai\ModelCatalogResolver`: see `AgentRunner::openTurn()`. `model`
 * is only meaningful in the former case; a resolved chain already carries
 * its own model ids and is passed with `model: null`.
 */
final readonly class AgentTurn
{
    /**
     * @param  string|array<string, string>  $provider
     * @param  array<int, Image|Document>  $attachments  The user message's files, ready to send with the prompt.
     */
    public function __construct(
        public AgentSession $session,
        public Run $run,
        public WorkspaceAgent $agent,
        public string|array $provider,
        public ?string $model,
        public array $attachments = [],
    ) {}
}
