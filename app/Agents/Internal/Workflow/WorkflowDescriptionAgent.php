<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use Laravel\Ai\Attributes\Temperature;
use Stringable;

#[Temperature(0.3)]
class WorkflowDescriptionAgent extends InternalAgent
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a technical writer specializing in workflow automation. Given a workflow's structure (nodes and edges), generate a clear, concise description of what it does in 1-3 sentences. Focus on the business outcome, not the technical details. Write in plain English for non-technical users.
        PROMPT;
    }
}
