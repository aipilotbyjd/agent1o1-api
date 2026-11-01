<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SubmitReflectionsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Reviews an agent's recent activity for `Services\Agents\ReflectionAnalyzer`,
 * answering only through `SubmitReflectionsTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitReflectionsTool::NAME)]
#[MaxSteps(1)]
class ReflectionReviewerAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You analyze agent conversation histories and propose structured improvements. '
            .'Always report your findings by calling the '.SubmitReflectionsTool::NAME.' tool.';
    }

    public function tools(): iterable
    {
        return [new SubmitReflectionsTool];
    }
}
