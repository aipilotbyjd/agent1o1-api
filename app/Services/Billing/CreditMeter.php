<?php

namespace App\Services\Billing;

use App\Ai\Usage\TokenUsage;
use App\Ai\Usage\UsagePricer;
use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Runs\NodeRun;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelPrices;
use Laravel\Pulse\Facades\Pulse;

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
     * The old ratio for a model with no price: credits per 1,000
     * prompt+completion tokens, rounded up. Only used when
     * `billing.unpriced_fallback.mode` is `flat`; by default an unpriced call
     * is billed at its cost tier's highest price instead.
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

    public function __construct(
        private readonly ModelPrices $prices,
        private readonly UsagePricer $pricer,
    ) {}

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
     * @param  array<string, mixed>  $usage
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
            'input_tokens' => self::TYPICAL_PROMPT_TOKENS,
            'output_tokens' => self::TYPICAL_COMPLETION_TOKENS,
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
        return $this->chatTurnBreakdown($usage)['total'];
    }

    /**
     * A chat turn's charge split into Gumloop's Chat Details lines. A turn
     * served by the workspace's own key pays nothing for reasoning, and its
     * orchestration fee is `billing.byok_orchestration_fee_rate` of what the
     * turn would have been worth on the platform's key ("16% instead of 8%,
     * calculated on what the run would have been worth").
     *
     * @param  array<string, mixed>|null  $usage
     * @return array{reasoning: int, tool_calls: int, compute: int, orchestration: int, waived_reasoning: int, total: int}
     */
    public function chatTurnBreakdown(?array $usage): array
    {
        $toolCalls = (int) ($usage['tool_call_count'] ?? 0) * self::CREDITS_PER_TOOL_CALL;
        $compute = $this->computeCost($usage['duration_seconds'] ?? null);

        if ($usage !== null && ByokProviderRegistrar::isByok($usage['provider'] ?? null)) {
            $waived = $this->listTokenCost($usage);
            $rate = (float) config('billing.byok_orchestration_fee_rate', config('billing.orchestration_fee_rate'));
            $orchestration = (int) ceil(($waived + $toolCalls + $compute) * $rate);

            return [
                'reasoning' => 0,
                'tool_calls' => $toolCalls,
                'compute' => $compute,
                'orchestration' => $orchestration,
                'waived_reasoning' => $waived,
                'total' => $toolCalls + $compute + $orchestration,
            ];
        }

        $reasoning = $this->tokenCost($usage);
        $subtotal = $reasoning + $toolCalls + $compute;
        $orchestration = $this->orchestrationFee($subtotal);

        return [
            'reasoning' => $reasoning,
            'tool_calls' => $toolCalls,
            'compute' => $compute,
            'orchestration' => $orchestration,
            'waived_reasoning' => 0,
            'total' => $subtotal + $orchestration,
        ];
    }

    /**
     * What a call's tokens would cost in credits on the platform's own key,
     * whoever's key actually served it. A BYOK call is priced as the
     * provider its key stands in for; one whose key or price is unknown
     * falls back to the flat per-token ratio.
     *
     * @param  array<string, mixed>  $usage
     */
    private function listTokenCost(array $usage): int
    {
        $provider = ByokProviderRegistrar::underlyingProvider($usage['provider'] ?? null) ?? ($usage['provider'] ?? null);
        $tokens = TokenUsage::fromArray($usage);
        $price = $this->priceFor([...$usage, 'provider' => $provider]) ?? $this->unpricedPrice([...$usage, 'provider' => $provider]);

        return $price !== null
            ? $this->dollarsToCredits($this->pricer->dollars($tokens, $price))
            : (int) ceil($tokens->totalTokens() / self::TOKENS_PER_CREDIT);
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

        $tokens = TokenUsage::fromArray($usage);
        $price = $this->priceFor($usage);

        if ($price === null) {
            $this->recordUnpriced($usage);
            $price = $this->unpricedPrice($usage);
        }

        $credits = $price !== null
            ? $this->dollarsToCredits($this->pricer->dollars($tokens, $price))
            : (int) ceil($tokens->totalTokens() / self::TOKENS_PER_CREDIT);

        $this->recordShadowDelta($usage, $tokens, $price, $credits);

        return $credits;
    }

    /**
     * The price an unpriced call is billed at: the conservative tier
     * fallback (`ModelPrices::fallbackFor()`), or null for the flat
     * per-token ratio when `billing.unpriced_fallback.mode` is `flat`.
     *
     * @param  array<string, mixed>  $usage
     * @return array{input: float, output: float, cache_read: float|null, cache_write: float|null}|null
     */
    private function unpricedPrice(array $usage): ?array
    {
        if (config('billing.unpriced_fallback.mode') === 'flat') {
            return null;
        }

        return $this->prices->fallbackFor($usage['provider'] ?? null, $usage['model'] ?? null);
    }

    /**
     * Counts calls billed without a price of their own, so the price book
     * report can show which models still need one.
     *
     * @param  array<string, mixed>  $usage
     */
    private function recordUnpriced(array $usage): void
    {
        Pulse::record(
            'ai_unpriced_call',
            ($usage['provider'] ?? 'unknown').':'.($usage['model'] ?? 'unknown'),
        )->count();
    }

    private function dollarsToCredits(float $dollars): int
    {
        return (int) ceil($dollars / (float) config('billing.credit_value_usd'));
    }

    /**
     * Records how many more (or fewer) credits this call costs than it would
     * have before the upgrade to Laravel AI 1.x, which started counting
     * cached and reasoning tokens: see `billing.shadow_billing`.
     *
     * @param  array<string, mixed>  $usage
     * @param  array{input: float, output: float, cache_read: float|null, cache_write: float|null}|null  $price
     */
    private function recordShadowDelta(array $usage, TokenUsage $tokens, ?array $price, int $credits): void
    {
        if (! config('billing.shadow_billing')) {
            return;
        }

        $legacyCredits = $price !== null
            ? $this->dollarsToCredits($this->pricer->legacyDollars($tokens, $price))
            : (int) ceil(($tokens->uncachedInputTokens() + $tokens->visibleOutputTokens()) / self::TOKENS_PER_CREDIT);

        if ($legacyCredits === $credits) {
            return;
        }

        Pulse::record(
            'ai_billing_shadow_delta',
            ($usage['provider'] ?? 'unknown').':'.($usage['model'] ?? 'unknown'),
            $credits - $legacyCredits,
        )->sum()->count();
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
        $price = $this->priceFor($usage);

        return $price === null ? null : $this->pricer->dollars(TokenUsage::fromArray($usage), $price);
    }

    /**
     * What a call cost the platform in provider fees: zero on a workspace's
     * own key, its own price when it has one, and the conservative fallback
     * price otherwise, so an unpriced call is never counted as free.
     *
     * @param  array<string, mixed>|null  $usage
     * @return array{usd: float, priced: bool, status: string, version_id: string|null}
     */
    public function providerCost(?array $usage): array
    {
        if ($usage === null || ! isset($usage['provider'])) {
            return ['usd' => 0.0, 'priced' => false, 'status' => 'none', 'version_id' => null];
        }

        if (ByokProviderRegistrar::isByok($usage['provider'])) {
            return ['usd' => 0.0, 'priced' => true, 'status' => 'byok', 'version_id' => null];
        }

        $price = $this->priceFor($usage);
        $meta = $price === null ? null : $this->prices->meta($usage['provider'], $usage['model'] ?? null);
        $price ??= $this->prices->fallbackFor($usage['provider'], $usage['model'] ?? null);

        return [
            'usd' => round($this->pricer->dollars(TokenUsage::fromArray($usage), $price), 8),
            'priced' => $meta !== null,
            'status' => $meta === null ? 'unpriced' : $meta['status']->value,
            'version_id' => $meta['version_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array{input: float, output: float, cache_read: float|null, cache_write: float|null}|null
     */
    private function priceFor(array $usage): ?array
    {
        if (! isset($usage['provider'], $usage['model'])) {
            return null;
        }

        return $this->prices->for($usage['provider'], $usage['model']);
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
