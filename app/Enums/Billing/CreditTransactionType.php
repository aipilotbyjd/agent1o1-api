<?php

namespace App\Enums\Billing;

/**
 * `credit_transactions.source_type`: what caused the charge.
 */
enum CreditTransactionType: string
{
    case NodeRun = 'node_run';
    case AgentStep = 'agent_step';

    /**
     * One graded case of an agent eval suite. Kept distinct from `AgentStep`
     * so eval spend can be told apart from production traffic on the ledger,
     * and because idempotency is per `(source_type, source_id)`, and an
     * `AgentEvalCaseResult` id would otherwise collide with an
     * `AgentMessage` id.
     */
    case EvalCase = 'eval_case';

    /**
     * One automatic QA grading of a live `AgentSession`: see
     * `Services\Agents\SessionEvaluator`. Kept distinct from `AgentStep` for
     * the same ledger-clarity and idempotency-key reasons as `EvalCase`.
     */
    case SessionEvaluation = 'session_evaluation';

    /**
     * One completed review of an agent's recent activity: see
     * `Services\Agents\ReflectionAnalyzer`. Skipped reviews are never charged.
     */
    case Reflection = 'reflection';

    /**
     * Drafting a new agent from a description, or rewriting an agent's
     * instructions: see `AgentDraftController` and
     * `AgentInstructionsController`. A draft saves nothing, so its charge
     * carries an id of its own.
     */
    case AgentDraft = 'agent_draft';

    /**
     * Drafting a new skill from a description: see `SkillDraftController`.
     * A draft saves nothing, so its charge carries an id of its own.
     */
    case SkillDraft = 'skill_draft';

    /**
     * Trying a model from the model picker with a sample prompt: see
     * `ModelTrialController`. A trial saves nothing, so its charge carries
     * an id of its own.
     */
    case ModelTrial = 'model_trial';

    /**
     * One workflow-builder assistant turn (charged against its assistant
     * message's id) or one builder assist call (suggest, configure,
     * explain) which saves nothing and carries an id of its own. See
     * `ProcessWorkflowBuilderMessageJob` and `WorkflowBuilderAssistant`.
     */
    case WorkflowBuilder = 'workflow_builder';

    /**
     * Having AI design or rework a page: see `GeneratePageJob`. Charged
     * against the generation's id, whether or not its result is applied.
     */
    case PageGeneration = 'page_generation';

    /**
     * A node an agent ran as a tool (`Ai\Tools\NodeTool`): the node's own
     * cost beyond the one credit its tool call already adds to the turn: a
     * fixed `billing.node_costs` surcharge, and the tokens an AI node spent
     * on its own model call. Saves nothing, so it carries an id of its own.
     */
    case AgentToolNode = 'agent_tool_node';

    /**
     * Embedding text into the workspace knowledge base: see
     * `Services\Agents\KnowledgeBase::ingest()`. Charged against the first
     * stored chunk's id.
     */
    case KnowledgeIngestion = 'knowledge_ingestion';

    /**
     * Smart mode's reviewer judging one agent action: see
     * `Services\Agents\Approvals\ActionReviewer`. Charged against the
     * action it reviewed.
     */
    case ActionReview = 'action_review';

    /**
     * One turn of a member's personal assistant (`AssistantTurn`), priced
     * like an agent chat turn.
     */
    case AssistantTurn = 'assistant_turn';

    /**
     * Minutes the assistant's cloud computer spent running code.
     */
    case AssistantSandbox = 'assistant_sandbox';
}
