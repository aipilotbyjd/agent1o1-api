<?php

namespace App\Services\Agents;

use App\Actions\Artifacts\StoreArtifactAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\ExportArtifactTool;
use App\Ai\Tools\ForgetTool;
use App\Ai\Tools\ImportSkillsTool;
use App\Ai\Tools\InvokeAgentTool;
use App\Ai\Tools\NodeTool;
use App\Ai\Tools\ReadKnowledgeDocumentTool;
use App\Ai\Tools\RecallMemoriesTool;
use App\Ai\Tools\RememberTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Ai\Tools\SubmitPlanTool;
use App\Ai\Tools\UpdateInstructionsTool;
use App\Ai\Tools\UpdateSkillTool;
use App\Ai\Tools\UseSkillTool;
use App\Ai\Tools\WaitForSubagentsTool;
use App\Ai\Tools\WorkflowTool;
use App\Contracts\DeclaresEffect;
use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Services\Agents\Approvals\ActionContext;
use App\Services\Agents\Approvals\ActionGate;
use App\Services\Agents\Approvals\ActionGuard;
use App\Services\Agents\Approvals\AutonomyResolver;
use App\Services\Agents\Approvals\PlanTracker;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Artifacts\DocumentRenderer;
use App\Services\Workflows\NodeRegistry;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\SupportsWebFetch;
use Laravel\Ai\Contracts\Providers\SupportsWebSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Builds the actual `Laravel\Ai` tool list handed to the model at agent-run
 * time: one `NodeTool` per attached `agent_node` row (types no longer in
 * `NodeRegistry`, e.g. a removed custom node, are silently skipped rather
 * than erroring the whole turn), one `WorkflowTool` per attached `Workflow`,
 * a `UseSkillTool` when the agent has skills attached, and a `RememberTool`
 * attached unconditionally so every agent can save durable facts. See docs/AGENTS_PLAN.md's "Models & tool binding" and
 * "Knowledge / RAG" sections.
 *
 * Knowledge tools (`SearchKnowledgeTool`/`ReadKnowledgeDocumentTool`) follow
 * Gumloop's "attach a source to use it" model: see `knowledgeTools()`.
 *
 * `ExportArtifactTool` is only attached when there's an `AgentSession` to
 * file an artifact under: normally `$run->runnable` for a session-backed
 * chat turn, or the explicit `$session` a caller with no session-backed
 * `$run` passes in (`AgentRunner::askInConversation()`, for an Agent node's
 * own conversation). `ask()`'s stateless embedded calls pass neither and so
 * skip it, same as before. `UpdateInstructionsTool` has the same session
 * requirement, so a stateless eval case can never rewrite the agent under
 * test, and is only attached when the agent has `allow_self_updates` on.
 * `CreateSkillTool`/`ImportSkillsTool`/`UpdateSkillTool` follow the same rule, gated on
 * `allow_skill_editing` instead. `InvokeAgentTool` also needs a session, and is
 * only offered in a top-level conversation: see `subagentTools()`.
 *
 * Web search and page fetching are the SDK's provider-native `WebSearch`/
 * `WebFetch`, run by the model provider itself: see `webTools()`. Being
 * run by the provider, they can't be gated per call; fetching is simply
 * off for an agent with `allow_web_fetch` unset.
 *
 * Every tool that can change something (attached nodes and workflows, and
 * the agent's own instruction/skill editing) is put behind an
 * `ActionGuard` for this turn's `ActionContext`, so the agent's autonomy
 * mode, tool rules and the workspace guardrails decide each call (see
 * `Approvals\ActionGate`). `$canPause` is false for a stateless call with no
 * conversation to resume. In Plan mode the agent also gets
 * `SubmitPlanTool`.
 */
class ToolRegistry
{
    public function __construct(
        private readonly NodeRegistry $nodes,
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly StoreArtifactAction $storeArtifact,
        private readonly KnowledgeBase $knowledgeBase,
        private readonly SkillInjector $skillInjector,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly AiManager $ai,
        private readonly DocumentRenderer $documentRenderer,
        private readonly AutonomyResolver $autonomy,
        private readonly ActionGate $gate,
        private readonly PlanTracker $plans,
    ) {}

    /**
     * @return array<int, NodeTool|WorkflowTool|SearchKnowledgeTool|ReadKnowledgeDocumentTool|UseSkillTool|CreateSkillTool|ImportSkillsTool|UpdateSkillTool|RememberTool|ForgetTool|RecallMemoriesTool|ExportArtifactTool|UpdateInstructionsTool|InvokeAgentTool|WaitForSubagentsTool|SubmitPlanTool|WebSearch|WebFetch>
     */
    public function toolsFor(Agent $agent, Run $run, ?AgentSession $session = null, bool $canPause = true, bool $simulateActions = false): array
    {
        $session ??= $run->runnable instanceof AgentSession ? $run->runnable : null;

        $context = $this->autonomy->contextFor($agent, $run, $session, $canPause, $simulateActions);

        $nodeTools = $agent->toolBindings()
            ->get()
            ->filter(fn (AgentToolBinding $binding) => $this->nodes->has($binding->node_type))
            ->map(fn (AgentToolBinding $binding) => (new NodeTool($this->nodes->resolve($binding->node_type), $binding, $run))
                ->guardedBy($this->guard($context, $binding->node_type, ActionToolKind::Node, $binding->approval_policy)));

        $workflowTools = $agent->workflows()
            ->with('currentVersion')
            ->get()
            ->map(function (Workflow $workflow) use ($context): WorkflowTool {
                $tool = new WorkflowTool($workflow, $this->startWorkflowRun, $this->workflowEffect($workflow));

                return $tool->guardedBy($this->guard($context, $tool->name(), ActionToolKind::Workflow, $this->pivotPolicy($workflow)));
            });

        $knowledgeTools = $this->knowledgeTools($agent);

        $skillTools = $agent->skills->isNotEmpty() ? [new UseSkillTool($agent)] : [];

        $memoryTools = array_values(array_filter([
            new RememberTool($agent, $run->triggered_by, $session?->id),
            new ForgetTool($agent, $run->triggered_by),
            RecallMemoriesTool::isNeededFor($agent, $run->triggered_by) ? new RecallMemoriesTool($agent, $run->triggered_by) : null,
        ]));

        $artifactTools = $session !== null
            ? [new ExportArtifactTool($agent, $session, $run, $this->storeArtifact, $this->documentRenderer)]
            : [];

        $skillEditingTools = $session !== null && $agent->allow_skill_editing
            ? array_values(array_filter([
                (new CreateSkillTool($agent, $run->triggered_by))->guardedBy($this->guard($context, CreateSkillTool::NAME, ActionToolKind::Builtin)),
                (new ImportSkillsTool($agent, $run->triggered_by))->guardedBy($this->guard($context, ImportSkillsTool::NAME, ActionToolKind::Builtin)),
                $agent->skills->isNotEmpty()
                    ? (new UpdateSkillTool($agent, $run->triggered_by))->guardedBy($this->guard($context, UpdateSkillTool::NAME, ActionToolKind::Builtin))
                    : null,
            ]))
            : [];

        $selfUpdateTools = [];

        if ($session !== null && $agent->allow_self_updates) {
            $tool = new UpdateInstructionsTool($agent, $this->skillInjector, $run->triggered_by, $session);
            $selfUpdateTools[] = $tool->guardedBy($this->guard($context, $tool->name(), ActionToolKind::Builtin));
        }

        $subagentTools = $session !== null ? $this->subagentTools($agent, $session, $context) : [];

        $gatedTools = [...$nodeTools->values()->all(), ...$workflowTools->values()->all(), ...$skillEditingTools, ...$selfUpdateTools];

        $planTools = $context->mode === AutonomyMode::Plan && $session !== null && $gatedTools !== []
            ? [new SubmitPlanTool($session, $run, $this->plans, array_map(fn ($tool): string => $tool->name(), $gatedTools))]
            : [];

        return [
            ...$nodeTools->values()->all(),
            ...$workflowTools->values()->all(),
            ...$knowledgeTools,
            ...$skillTools,
            ...$skillEditingTools,
            ...$memoryTools,
            ...$artifactTools,
            ...$selfUpdateTools,
            ...$subagentTools,
            ...$planTools,
            ...$this->webTools($agent),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    private function guard(ActionContext $context, string $toolName, ActionToolKind $kind, ?array $policy = null): ActionGuard
    {
        return new ActionGuard($this->gate, $this->plans, $context, $toolName, $kind, $policy);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pivotPolicy(Workflow $workflow): ?array
    {
        $policy = $workflow->pivot?->approval_policy;

        return is_string($policy) ? json_decode($policy, true) : $policy;
    }

    /**
     * The strongest effect of any node in the workflow's published graph:
     * running the workflow does everything its nodes do. A workflow with no
     * published graph, or a node that doesn't declare an effect, counts as a
     * write.
     */
    private function workflowEffect(Workflow $workflow): ActionEffect
    {
        $nodes = $workflow->currentVersion?->graph['nodes'] ?? null;

        if ($nodes === null) {
            return ActionEffect::Write;
        }

        $effects = collect($nodes)
            ->reject(fn (array $node): bool => $this->nodes->isFlowControl((string) ($node['type'] ?? '')))
            ->map(function (array $node): ActionEffect {
                $type = (string) ($node['type'] ?? '');

                if (! $this->nodes->has($type)) {
                    return ActionEffect::Write;
                }

                $instance = $this->nodes->resolve($type);

                return $instance instanceof DeclaresEffect ? $instance->effect($node['config'] ?? []) : ActionEffect::Write;
            });

        return ActionEffect::strongest(...$effects->all());
    }

    /**
     * `InvokeAgentTool` for a top-level conversation only: "Me" when the
     * agent allows self-cloning, plus its attached subagents. A subagent's
     * own conversation never delegates further: its job runs on an
     * `ai-subagent` worker, and one that waited on children queued behind it
     * on the same workers could leave every worker waiting and none free to
     * run them.
     *
     * Targets are keyed by the name the model uses, so a subagent whose name
     * clashes with "Me" or another subagent gets a numbered suffix rather
     * than silently replacing it.
     *
     * @return array<int, InvokeAgentTool|WaitForSubagentsTool>
     */
    private function subagentTools(Agent $agent, AgentSession $session, ActionContext $context): array
    {
        if ($session->parent_session_id !== null) {
            return [];
        }

        $targets = [];

        if ($agent->allow_self_clone) {
            $targets[InvokeAgentTool::SELF] = $agent;
        }

        foreach ($agent->subagents as $subagent) {
            $targets[$this->uniqueTargetName($subagent->name, $targets)] = $subagent;
        }

        return $targets === [] ? [] : [new InvokeAgentTool($session, $targets, $context->mode, $context->testMode), new WaitForSubagentsTool($session)];
    }

    /**
     * @param  array<string, Agent>  $targets
     */
    private function uniqueTargetName(string $name, array $targets): string
    {
        $taken = array_map(mb_strtolower(...), array_keys($targets));
        $candidate = $name;

        for ($suffix = 2; in_array(mb_strtolower($candidate), $taken, true); $suffix++) {
            $candidate = "{$name} ({$suffix})";
        }

        return $candidate;
    }

    /**
     * Only offered when every provider in the agent's failover chain runs
     * the tool natively: an unsupported provider (any `openai-compatible`
     * gateway, for one) throws on a provider tool rather than ignoring it,
     * which would fail the whole turn once failover reached it.
     *
     * @return array<int, WebSearch|WebFetch>
     */
    private function webTools(Agent $agent): array
    {
        [$provider] = $this->modelCatalog->forAgent($agent);

        $providers = collect(is_array($provider) ? array_keys($provider) : [$provider])
            ->map(fn (?string $name) => $this->ai->textProvider($name));

        return array_values(array_filter([
            $providers->every(fn ($instance) => $instance instanceof SupportsWebSearch) ? new WebSearch : null,
            $agent->allow_web_fetch && $providers->every(fn ($instance) => $instance instanceof SupportsWebFetch) ? new WebFetch : null,
        ]));
    }

    /**
     * Scoped to what this agent may actually search: its explicitly
     * attached `AgentKnowledgeCollection`s, plus its own exported artifacts
     * (always implicitly searchable: see `Agent::artifactKnowledgeCollection()`).
     * An agent with neither falls back to every collection in the
     * workspace, the zero-config behavior this had before per-agent
     * attachment existed.
     *
     * @return array<int, SearchKnowledgeTool|ReadKnowledgeDocumentTool>
     */
    private function knowledgeTools(Agent $agent): array
    {
        $attached = $agent->knowledgeCollections()->pluck('collection')->all();

        $artifactCollection = $agent->artifactKnowledgeCollection();
        $hasOwnArtifactChunks = DocumentEmbedding::query()
            ->shared()
            ->where('workspace_id', $agent->workspace_id)
            ->where('collection', $artifactCollection)
            ->exists();

        $scoped = $hasOwnArtifactChunks ? [...$attached, $artifactCollection] : $attached;

        $collection = match (true) {
            $scoped !== [] => $scoped,
            DocumentEmbedding::query()->shared()->where('workspace_id', $agent->workspace_id)->exists() => null,
            default => false,
        };

        if ($collection === false) {
            return [];
        }

        return [
            new SearchKnowledgeTool($agent->workspace, $this->knowledgeBase, $collection),
            new ReadKnowledgeDocumentTool($agent->workspace, $this->knowledgeBase, $collection),
        ];
    }
}
