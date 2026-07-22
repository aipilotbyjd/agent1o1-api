<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

#[Temperature(0.2)]
#[MaxSteps(1)]
#[Timeout(15)]
class WorkflowNamingAgent extends InternalAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Generate a short, descriptive title for a workflow automation from the user's description.

        Rules:
        - 3 to 6 words maximum
        - Title Case
        - Action-oriented (what the workflow does)
        - No filler words ("workflow", "automation", "system")

        Examples:
          "webhook arrives and post to slack" → "Webhook to Slack Notifier"
          "every day fetch reports and email team" → "Daily Report Email"
          "github PR merged notify the channel" → "GitHub PR Merge Alert"
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
        ];
    }
}
