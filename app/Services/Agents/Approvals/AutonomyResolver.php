<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Runs\Run;

/**
 * Works out the mode a conversation actually runs under: its own override
 * (set by a person, a trigger, or the parent of a subagent) or else the
 * agent's mode, never looser than the workspace's `max_autonomy_mode`.
 */
class AutonomyResolver
{
    public function modeFor(Agent $agent, ?AgentSession $session = null, ?WorkspaceAgentPolicy $policy = null): AutonomyMode
    {
        $policy ??= WorkspaceAgentPolicy::forWorkspace($agent->workspace_id);

        $requested = $session?->autonomy_mode ?? $agent->autonomy_mode ?? AutonomyMode::Ask;

        return AutonomyMode::strictest($requested, $policy->max_autonomy_mode);
    }

    public function testModeFor(Agent $agent, ?AgentSession $session = null): bool
    {
        return $session?->test_mode ?? $agent->test_mode ?? false;
    }

    /**
     * Tells the model how it may act in this conversation, so it plans
     * around approvals instead of being surprised by them, e.g. it warns
     * the user a send needs their OK, or proposes a plan in Plan mode.
     */
    public function withNote(string $instructions, Agent $agent, ?AgentSession $session = null): string
    {
        $note = match ($this->modeFor($agent, $session)) {
            AutonomyMode::ReadOnly => 'You are read-only in this conversation: you can look things up, but any action that changes something will be blocked. Offer to explain what you would do instead.',
            AutonomyMode::Ask => 'Actions that change something (sending, posting, creating, editing, deleting) need the user\'s approval before they run. When you call one, the conversation pauses until they decide; if they reject it, respect their note.',
            AutonomyMode::Plan => 'You are in Plan mode: before taking any action that changes something, submit a plan with submit_plan and wait for approval. Once approved, carry out exactly the planned steps.',
            AutonomyMode::Smart => 'Low-risk actions run straight away; riskier ones pause for the user\'s approval. If an action is rejected, respect their note.',
            AutonomyMode::Autopilot => 'Most actions run without asking, but destructive ones (deleting things) pause for the user\'s approval.',
        };

        if ($this->testModeFor($agent, $session)) {
            $note .= ' Test run is on: actions are simulated and nothing is really sent or changed. Say so when you report what you did.';
        }

        return trim($instructions)."\n\n## How you may act\n".$note;
    }

    public function contextFor(Agent $agent, Run $run, ?AgentSession $session, bool $canPause = true, bool $simulateActions = false): ActionContext
    {
        $policy = WorkspaceAgentPolicy::forWorkspace($agent->workspace_id);

        return new ActionContext(
            agent: $agent,
            run: $run,
            session: $session,
            mode: $this->modeFor($agent, $session, $policy),
            testMode: $simulateActions || $this->testModeFor($agent, $session),
            canPause: $canPause && $session !== null,
            policy: $policy,
        );
    }
}
