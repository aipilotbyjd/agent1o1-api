<?php

namespace App\Ai\Tools\FollowUps;

use App\Models\Agents\Agent;
use App\Models\Triggers\Trigger;
use App\Services\Agents\AgentFollowUps;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The follow-ups an agent has waiting for the person it's working for, so
 * it can answer "what are you keeping an eye on?" and find the id to cancel
 * one. Read-only, so it runs without approval.
 */
class ListFollowUpsTool implements Tool
{
    public const string NAME = 'list_follow_ups';

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
        return 'Lists the follow-ups you have scheduled for this user that are still waiting to run, soonest first, with the id each needs to be cancelled.';
    }

    public function handle(Request $request): Stringable|string
    {
        $followUps = $this->followUps->active($this->agent, $this->userId, forUser: true);

        if ($followUps->isEmpty()) {
            return 'You have no follow-ups scheduled for this user.';
        }

        return json_encode(
            $followUps->map(fn (Trigger $trigger): array => $this->followUps->describe($trigger))->values()->all(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
