<?php

namespace App\Ai\Tools;

use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Services\Agents\Approvals\PlanTracker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Plan mode's one way forward: the agent lays out the actions it intends to
 * take, a person approves (or edits, or rejects) the plan, and from then on
 * a call matching a pending step runs without asking: see
 * `ActionGate::planVerdict()`. Only offered by `ToolRegistry` in Plan mode.
 *
 * A new plan replaces any still open in the conversation, so the agent can
 * revise one after feedback.
 */
class SubmitPlanTool implements Tool
{
    public const NAME = 'submit_plan';

    /**
     * @param  list<string>  $toolNames  the tools the plan's steps may use
     */
    public function __construct(
        private readonly AgentSession $session,
        private readonly Run $run,
        private readonly PlanTracker $plans,
        private readonly array $toolNames,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'You are in Plan mode: before taking any action that changes something, propose the actions you will take as a plan. '
            .'List every step, in order, with the tool it uses and the key values it commits to (who receives it, which channel, which file). '
            .'After submitting, stop and tell the user the plan is waiting for their approval. Once approved, carry out exactly those steps; '
            .'anything outside the plan will need separate approval. Looking things up never needs a plan.';
    }

    public function handle(Request $request): Stringable|string
    {
        $steps = collect((array) ($request['steps'] ?? []))
            ->filter(fn (mixed $step): bool => is_array($step) && in_array($step['tool'] ?? null, $this->toolNames, true) && filled($step['summary'] ?? null))
            ->map(fn (array $step): array => [
                'tool' => (string) $step['tool'],
                'summary' => (string) $step['summary'],
                'arguments' => is_array($step['arguments'] ?? null) ? $step['arguments'] : [],
            ])
            ->values()
            ->all();

        if ($steps === []) {
            return json_encode(['error' => 'The plan has no usable steps. Each step needs a tool from: '.implode(', ', $this->toolNames).', and a summary.']) ?: '{}';
        }

        $plan = $this->plans->propose(
            $this->session,
            $this->run->id,
            trim((string) ($request['title'] ?? '')) ?: 'Plan',
            filled($request['summary'] ?? null) ? (string) $request['summary'] : null,
            $steps,
        );

        return json_encode([
            'plan_id' => $plan->id,
            'status' => 'waiting_for_approval',
            'note' => 'Stop here and tell the user the plan needs their approval before you act.',
        ]) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('A short name for the plan.')->required(),
            'summary' => $schema->string()->description('One or two sentences on what the plan achieves.'),
            'steps' => $schema->array()->items($schema->object(fn () => [
                'tool' => $schema->string()->enum($this->toolNames)->description('The tool this step uses.')->required(),
                'summary' => $schema->string()->description('What this step does, in plain words.')->required(),
                'arguments' => $schema->object(fn () => [])->description('The key values the step commits to, e.g. {"to": "ana@acme.com"}. Only include values that must not change.'),
            ]))->description('The actions, in order.')->required(),
        ];
    }
}
