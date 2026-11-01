<?php

namespace App\Nodes\AiAutomation;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Agents\AgentRunner;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Embeds an `Agent` inside a workflow: the Workflow→Agent direction (Stage 7's
 * `WorkflowTool` is the reverse, Agent→Workflow), and this app's version of
 * Gumloop's Agent node (docs/gumloop/output/raw/core-concepts/agent_node.md).
 * Ported design from the old project's `AgentStepHandler`: resolves the
 * `Agent` scoped to the run's workspace and prompts it via
 * `AgentRunner::askInConversation()`, which persists the turn as a real
 * conversation (see that method's docblock for why, and for how
 * `previous_conversation_id`/`conversation_id` continuity works) and returns
 * `{text, usage, conversation_id, messages, attachment_names}`. `usage` lands
 * on `NodeRun.usage` the same way `AskAiNode`'s does
 * (`WorkflowRunner::executeNodeContract()`), which is all `CreditMeter` needs;
 * no separate credit-accounting wiring for this node beyond its
 * `config('billing.node_costs.agent')` surcharge.
 *
 * `config.prompt` is already `{{ }}`-resolved by the time `execute()` sees it
 * (`WorkflowRunner`'s templating pass runs before any node type): omitting
 * it falls back to `input.message` from the run's own input, mirroring the
 * old project's default.
 */
class AgentNode implements DeclaresEffect, HasIcon, NodeContract
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function type(): string
    {
        return 'agent';
    }

    public function category(): string
    {
        return NodeCategory::AiAutomation->value;
    }

    public function name(): string
    {
        return 'Agent';
    }

    public function icon(): string
    {
        return 'robot-01';
    }

    public function description(): string
    {
        return 'Prompts one of this workspace\'s Agents (with its instructions, skills, and tools) and returns its reply.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                'agent_id' => Field::dynamic('Agent', 'workspace.agents', allowCustom: false),
                'prompt' => Field::textarea('Message', 'What to send the agent. Defaults to the run input\'s message.'),
                'previous_conversation_id' => Field::advanced(Field::text('Continue conversation', 'A conversation ID from an earlier Agent step, to keep its context.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $agent = Agent::query()
            ->where('id', $config['agent_id'] ?? null)
            ->where('workspace_id', $run->workspace_id)
            ->first();

        if ($agent === null) {
            throw new InvalidArgumentException("Agent [{$config['agent_id']}] does not exist in this workspace.");
        }

        $prompt = $config['prompt'] ?? (string) Arr::get($context, 'input.message', '');

        return $this->runner->askInConversation($agent, $run, $prompt, $config['previous_conversation_id'] ?? null);
    }
}
