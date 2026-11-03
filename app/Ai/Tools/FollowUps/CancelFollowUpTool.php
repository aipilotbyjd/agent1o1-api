<?php

namespace App\Ai\Tools\FollowUps;

use App\Models\Agents\Agent;
use App\Services\Agents\AgentFollowUps;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Switches off one of the agent's own follow-ups when the person no longer
 * wants it. Only a follow-up set while working for that same person, and
 * only stopping work the agent would otherwise do later, so it runs without
 * approval, like forgetting a memory.
 */
class CancelFollowUpTool implements Tool
{
    public const string NAME = 'cancel_follow_up';

    public function __construct(
        private readonly Agent $agent,
        private readonly AgentFollowUps $followUps,
        private readonly ?string $userId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Cancels one of your scheduled follow-ups by its id (from `'.ListFollowUpsTool::NAME.'` or from when you scheduled it), '
            .'when the user no longer wants it or the thing you were waiting for has happened.';
    }

    public function handle(Request $request): Stringable|string
    {
        $trigger = $this->followUps->cancel($this->agent, trim((string) ($request['id'] ?? '')), $this->userId);

        if ($trigger === null) {
            return 'No follow-up of yours for this user has that id. List them with `'.ListFollowUpsTool::NAME.'` to find the right one.';
        }

        $title = $trigger->config['follow_up']['title'] ?? 'the follow-up';

        return "Cancelled \"{$title}\". It will not run again.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The follow-up\'s id.')->required(),
        ];
    }
}
