<?php

namespace App\Services\Billing;

use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Runs\NodeRun;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelPrices;

/**
 * The per-node/per-agent-step cost table docs/PLAN.md's "Architecture
 * Overview" describes: "1 base credit/run, AI nodes billed by tokens, some
 * integration nodes have fixed costs". Per-node fixed costs are read from
 * `config('billing.node_costs')`: see that file for how to price a node
 * type.
 *
 * A call served by a workspace's own provider key (bring your own key:
 * see `ByokProviderRegistrar`) costs no token credits: the workspace pays
 * the provider directly. Its tokens still sit on the usage record for
 * visibility, and the rest of the formula (base credit, node surcharge,
 * tool calls, compute, orchestration) is still billed, since that work
 * runs here either way.
 *
 * Workflow node runs and agent chat turns are priced by two different
 * Gumloop formulas (see docs/gumloop/output/raw/core-concepts/credits.md):
 * a node run is `1 base credit + its fixed node cost + its token cost`,
 * with no compute or orchestration fee, while a chat turn is
 * `reasoning + tool calls + compute`, plus an orchestration fee on that
 * subtotal: `costForNodeRun` implements the former, `costForChatTurn` (used
 * by every agent-turn/eval/session-evaluation method below, since all three
 * are "priced identically to a production turn") implements the latter.
 */
class CreditMeter
{
    /**
     * Every node run costs at least this much just for having executed.
     */
    private const int BASE_CREDITS_PER_RUN = 1;

    /**
     * Fallback ratio for a model with no `config('billing.model_prices')`
     * entry: additional credits per 1,000 prompt+completion tokens, rounded
     * up. Used whenever the model that served a call isn't in the price
     * table (see that config entry for why most of this app's catalog isn't
     * priced yet).
     */
    private const int TOKENS_PER_CREDIT = 1000;

    /**
     * Credits per session-minute of active processing, and the floor below
     * which a turn still costs at least one credit: Gumloop's "5 credits
     * per session-minute of active processing, with a minimum of 1 credit
     * per response."
     */
    private const int COMPUTE_CREDITS_PER_MINUTE = 5;

    private const int MINIMUM_COMPUTE_CREDITS = 1;

    /**
     * 1 credit per successful tool call: Gumloop's "Tool Calls" component.
     */
    private const int CREDITS_PER_TOOL_CALL = 1;

    /**
     * The message `estimateChatMessage()` prices: a short conversation with
     * instructions in, a paragraph or two out.
     */
    private const int TYPICAL_PROMPT_TOKENS = 2000;

    private const int TYPICAL_COMPLETION_TOKENS = 500;

    public function __construct(private readonly ModelPrices $prices) {}

    /**
     * The size of the message `estimateChatMessage()` prices.
     *
     * @return array{prompt: int, completion: int}
     */
    public function typicalMessageTokens(): array
    {
        return ['prompt' => self::TYPICAL_PROMPT_TOKENS, 'completion' => self::TYPICAL_COMPLETION_TOKENS];
    }

    public function costForNodeRun(NodeRun $nodeRun): int
    {
        return self::BASE_CREDITS_PER_RUN
            + $this->fixedNodeCost($nodeRun->type)
            + $this->tokenCost($nodeRun->usage);
    }

    public function costForAgentMessage(AgentMessage $message): int
    {
        return $this->costForChatTurn($message->usage);
    }

    /**
     * One graded eval case. Priced identically to a production turn, because
     * it is one: the same agent, the same model, the same tokens.
     */
    public function costForEvalCase(AgentEvalCaseResult $result): int
    {
        return $this->costForChatTurn($result->usage);
    }

    /**
     * One automatic session grading. Priced the same as any other judge
     * call: it is one, on the same models, same tokens.
     */
    public function costForSessionEvaluation(AgentSessionEvaluation $evaluation): int
    {
        return $this->costForChatTurn($evaluation->usage);
    }

    public function costForReflectionRun(ReflectionRun $reflectionRun): int
    {
        return $this->costForChatTurn($reflectionRun->usage);
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public function costForAgentDraft(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public function costForSkillDraft(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * Trying a model from the picker: priced as the chat turn it is.
     *
     * @param  array<string, mixed>  $usage
     */
    public function costForModelTrial(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * A workflow-builder chat turn or assist call: priced as the chat turn
     * it is.
     *
     * @param  array<string, mixed>  $usage
     */
    public function costForWorkflowBuilder(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * One AI page design (`GeneratePageJob`): priced like a builder assist
     * call: the tokens, the one forced tool call and the compute time.
     *
     * @param  array<string, mixed>  $usage
     */
    public function costForPageGeneration(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * One Smart-mode review of an agent action: priced as the judge call
     * it is.
     *
     * @param  array<string, mixed>  $usage
     */
    public function costForActionReview(array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * One personal-assistant turn: the same chat-turn formula as an agent's.
     *
     * @param  array<string, mixed>|null  $usage
     */
    public function costForAssistantTurn(?array $usage): int
    {
        return $this->costForChatTurn($usage);
    }

    /**
     * A node an agent called as a tool. Its turn already bills one credit
     * for the call itself (`CREDITS_PER_TOOL_CALL`), which stands in for the
     * base credit a workflow node run would carry, so this is only what the
     * node costs on top: its fixed surcharge and its own tokens.
     *
     * @param  array<string, mixed>|null  $usage
     */
    public function costForAgentToolNode(string $type, ?array $usage): int
    {
        return $this->fixedNodeCost($type) + $this->tokenCost($usage);
    }

    /**
     * Embedding text for the knowledge base, priced by its input tokens.
     *
     * @param  array{prompt_tokens: int, provider?: string|null, model?: string|null}  $usage
     */
    public function costForEmbeddings(array $usage): int
    {
        return $this->tokenCost($usage);
    }

    /**
     * Roughly what one typical chat message costs on `$provider`/`$model`:
     * `TYPICAL_PROMPT_TOKENS` in, `TYPICAL_COMPLETION_TOKENS` out, no tool
     * calls, the compute floor, for the model picker's cost hint. A
     * workspace's own key (`ByokProviderRegistrar::PREFIX`) makes the token
     * part free, as it does for a real call.
     */
    public function estimateChatMessage(?string $provider, ?string $model): int
    {
        return $this->costForChatTurn([
            'prompt_tokens' => self::TYPICAL_PROMPT_TOKENS,
            'completion_tokens' => self::TYPICAL_COMPLETION_TOKENS,
            'provider' => $provider,
            'model' => $model,
        ]);
    }

    /**
     * Gumloop's agent-chat formula: Chat & Reasoning + Tool Calls + Compute,
     * then an Orchestration Fee on top of that subtotal. Unlike a workflow
     * node run, a chat turn has no separate flat base credit: Compute's own
     * one-credit floor already guarantees a minimum charge.
     *
     * @param  array<string, mixed>|null  $usage
     */
    private function costForChatTurn(?array $usage): int
    {
        $reasoning = $this->tokenCost($usage);
        $toolCalls = (int) ($usage['tool_call_count'] ?? 0) * self::CREDITS_PER_TOOL_CALL;
        $compute = $this->computeCost($usage['duration_seconds'] ?? null);

        $subtotal = $reasoning + $toolCalls + $compute;

        return $subtotal + $this->orchestrationFee($subtotal);
    }

    private function computeCost(int|float|null $durationSeconds): int
    {
        if ($durationSeconds === null) {
            return self::MINIMUM_COMPUTE_CREDITS;
        }

        return max(
            self::MINIMUM_COMPUTE_CREDITS,
            (int) ceil($durationSeconds / 60 * self::COMPUTE_CREDITS_PER_MINUTE),
        );
    }

    private function orchestrationFee(int $subtotal): int
    {
        $rate = (float) config('billing.orchestration_fee_rate');

        return (int) ceil($subtotal * $rate);
    }

    /**
     * @param  array<string, mixed>|null  $usage
     */
    private function tokenCost(?array $usage): int
    {
        if ($usage === null || ByokProviderRegistrar::isByok($usage['provider'] ?? null)) {
            return 0;
        }

        $dollarCost = $this->dollarCost($usage);

        if ($dollarCost !== null) {
            $creditValue = (float) config('billing.credit_value_usd');

            return (int) ceil($dollarCost / $creditValue);
        }

        $totalTokens = (int) ($usage['prompt_tokens'] ?? 0) + (int) ($usage['completion_tokens'] ?? 0);

        return (int) ceil($totalTokens / self::TOKENS_PER_CREDIT);
    }

    /**
     * Real dollar cost of this call, from `ModelPrices` keyed by the
     * `provider`/`model` the SDK reports back on `usage` (see
     * `AgentRunner::usageFor()`/`AskAiNode::execute()`). Null when the model
     * isn't priced: `tokenCost()` falls back to the flat ratio instead.
     *
     * @param  array<string, mixed>  $usage
     */
    public function dollarCost(array $usage): ?float
    {
        if (! isset($usage['provider'], $usage['model'])) {
            return null;
        }

        $price = $this->prices->for($usage['provider'], $usage['model']);

        if ($price === null) {
            return null;
        }

        return ((int) ($usage['prompt_tokens'] ?? 0) / 1_000_000 * $price['input'])
            + ((int) ($usage['completion_tokens'] ?? 0) / 1_000_000 * $price['output']);
    }

    /**
     * Flat surcharge for this node type, from `config('billing.node_costs')`.
     * Unlisted types cost nothing extra beyond the base credit.
     */
    private function fixedNodeCost(string $type): int
    {
        return (int) config("billing.node_costs.{$type}", 0);
    }
}
