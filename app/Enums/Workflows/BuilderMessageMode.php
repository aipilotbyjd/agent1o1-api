<?php

namespace App\Enums\Workflows;

/**
 * `workflow_builder_messages.mode`: what a user message asks the builder for;
 * its reply carries the same mode.
 * `Build` edits the draft; `Ask` only reads it, so the agent is handed no
 * editing tools for that turn (`WorkflowBuilderAgent::tools()`).
 */
enum BuilderMessageMode: string
{
    case Build = 'build';
    case Ask = 'ask';
}
