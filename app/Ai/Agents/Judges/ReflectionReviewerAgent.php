<?php

namespace App\Ai\Agents\Judges;

use App\Ai\Agents\Concerns\SubmitsStructuredOutput;
use App\Ai\Tools\Submissions\SubmitReflectionsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Reviews an agent's recent activity for `Services\Agents\ReflectionAnalyzer`,
 * answering only through `SubmitReflectionsTool`.
 */
#[MaxSteps(1)]
class ReflectionReviewerAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable, SubmitsStructuredOutput;

    public function instructions(): string
    {
        return 'You analyze agent conversation histories and propose structured improvements. '
            .'Always report your findings as your structured result.';
    }

    protected function submissionTool(): Tool
    {
        return new SubmitReflectionsTool;
    }
}
