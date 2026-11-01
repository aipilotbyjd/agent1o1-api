# Workflows + Agents — Single Implementation Plan

## Where this fits

`docs/PLAN.md` Phase 1 (Foundations) and Phase 4 (Triggers) are already built — `Workspace`/`User`/`ApiKey`/Sanctum-Passport auth exist, and `Trigger`/`TriggerEvent`/`TriggerPreset` + the polling/webhook pipeline are already implemented (see `app/Models/Triggers/*`, `app/Jobs/Triggers/*`, `app/Services/Triggers/*`).

Nothing under `Models/Workflows`, `Models/Agents`, `Services/Workflows`, `Services/Agents`, `Ai/`, or `Nodes/` exists yet. `docs/WORKFLOWS_PLAN.md` and `docs/AGENTS_PLAN.md` are the authoritative design docs (schema, engine behavior, security model) — **this doc does not replace them**, it is the single ordered build sequence that interleaves both, since Agents depends on Workflows at several points (an `Agent`'s tools include Workflows; `AgentNode` embeds an agent inside a workflow graph; both share the same `runs` table and `NodeContract`/`NodeRegistry`).

Rule for the whole build: **only create new files** (migrations, models, services, jobs, controllers, routes, tests). Never delete or rewrite the existing Trigger/Auth/Workspace code — the two systems are additive; `Trigger` rows will later point at `workflow_id` the same way they already point at other targets (`TriggerTargetType`), and workflow trigger-type nodes (`ManualTriggerNode`/`ScheduleTriggerNode`/`WebhookTriggerNode`) sit on top of the existing `Trigger` machinery, they don't replace it.

## Build order (11 stages, each independently shippable/testable)

### Stage 1 — Workflow schema + registry skeleton
*(`WORKFLOWS_PLAN.md` build-order step 1-2)*

- Migrations: `workflows`, `workflow_versions`, `workflow_nodes`, `workflow_edges`, `runs`, `node_runs`, `node_categories`, `custom_nodes`.
- Models: `Models/Workflows/{Workflow,WorkflowVersion,WorkflowNode,WorkflowEdge}.php`, `Models/Runs/{Run,NodeRun}.php`, `Models/Nodes/{NodeCategory,CustomNode}.php`.
- `Enums/PortType.php`, `Enums/NodeCategory.php`, `Enums/RunStatus.php`, `Enums/NodeRunStatus.php`, `Enums/Queue.php` (queue names only — full Horizon supervisor wiring can lag until Stage 4 jobs exist).
- `database/seeders/NodeCategoriesDataset.php` (core categories: `flow-logic`, `data-transform`, `ai-automation`, `custom`, `triggers-events`) — add its call to `DatabaseSeeder.php` (append, don't restructure the existing Trigger seeder call).
- `Contracts/NodeContract.php`, `Services/Workflows/NodeRegistry.php`, `Providers/NodeRegistryServiceProvider.php` (register in `bootstrap/providers.php`).
- Minimal node set to prove the contract: `Nodes/DataTransform/{TransformNode,CallApiNode}.php`, `Nodes/FlowLogic/{RouterNode,FilterNode}.php`, `Nodes/AiAutomation/AskAiNode.php` (via `laravel/ai`), `Nodes/DataTransform/RunCodeNode.php`.
- Factories: `WorkflowFactory`, `WorkflowVersionFactory`, `RunFactory`, `NodeRunFactory`.

### Stage 2 — Draft editing + validation
*(`WORKFLOWS_PLAN.md` step 3)*

- `Services/Workflows/{GraphValidator,ConfigSchemaValidator}.php`, `Workflow::replaceGraph()`/`publishVersion()` methods.
- Internal API: `Http/Controllers/Api/Internal/V1/Workflows/{WorkflowController,WorkflowVersionController,WorkflowBuilderController}.php`, `Requests/.../Workflows/{StoreWorkflowRequest,UpdateWorkflowRequest}.php`, `Resources/Internal/WorkflowResource.php`, `routes/api/internal/workflows.php` + `workflow_builder.php`, both required from the existing `routes/api/internal/index.php` (append `require` lines, don't touch existing ones).
- `Exceptions/WorkflowValidationException.php` mapped to 422 in `bootstrap/app.php`'s existing `->withExceptions()` block (add a case, don't restructure it).
- Feature test: build `Input → Transform → CallApi → Output` via Internal API, publish, assert `GraphValidator` rejects a cycle/dangling-edge/duplicate-key graph.

### Stage 3 — Execution engine (manual trigger, linear graphs)
*(`WORKFLOWS_PLAN.md` step 4)*

- `Actions/Workflows/StartWorkflowRunAction.php`, `Services/Workflows/WorkflowRunner.php`, `Services/Workflows/Engine/{GraphAdvancer,StepFailureHandler}.php`, `Services/Workflows/StepOptions.php`.
- `Jobs/Workflows/{ExecuteNodeJob,DispatchNextNodesJob,ResumeWorkflowJob}.php`, queued on `Queue::WorkflowDispatch`/`WorkflowExecute`.
- `Events/Runs/{RunCompleted,RunFailed}.php`, `Listeners/Workflows/{RecordRunCreditUsage,NotifyOnRunFailure}.php` (listener bodies can be stubs until Stage 7 billing exists — just wire the event dispatch).
- Internal API: `Http/Controllers/Api/Internal/V1/Runs/RunController.php`, `routes/api/internal/runs.php`; Public API: `Http/Controllers/Api/Public/V1/{Workflows/WorkflowController,Runs/RunController}.php`, `routes/api/public/{workflows.php,runs.php}`, both wired into `routes/api/public/index.php`.
- Update `config/horizon.php`: add `supervisor-workflows-dispatch`/`supervisor-workflows-execute` blocks alongside the existing trigger supervisors (append, don't touch trigger ones).
- Feature test: run the Stage 2 graph end-to-end via both API surfaces, assert `runs`/`node_runs` reach `completed` with `runnable_type = Workflow::class`.

### Stage 4 — Flow control: loops, sub-workflows, approvals, waits
*(`WORKFLOWS_PLAN.md` step 5)*

- `Services/Workflows/Engine/{LoopCoordinator,SubWorkflowCoordinator}.php`.
- Migrations + models: `workflow_approvals` table, `Models/Workflows/WorkflowApproval.php`.
- Node types (engine-driven, no `NodeContract`): `Nodes/FlowLogic/{LoopNode,SubWorkflowNode,HumanApprovalNode,WaitNode,JoinPathsNode,DelayNode}.php`.
- `WorkflowRunner::{resolveApproval,resolveCallback,expireWait,resolveSubWorkflow}()`.
- `Jobs/Triggers/` already has the scheduler pattern for polling — reuse the same approach for `Jobs/System/` (new file) that expires stale `Wait` callbacks on a schedule via `routes/console.php` (append a new `->everyMinute()` line, don't touch existing trigger schedules).
- Feature tests per `WORKFLOWS_PLAN.md`'s verification bullets (Loop 3-item fan-out + join, Router skip-branch, HumanApproval pause/resume, Wait callback + timeout).

### Stage 5 — Templating
*(`WORKFLOWS_PLAN.md` step 6)*

- `Services/Workflows/{TemplateResolver,ExpressionEvaluator,SafePattern,TemplatePaths}.php` — wire into `StepContextBuilder`/node config resolution before `execute()` is called.

### Stage 6 — Agent core: bare chat agent
*(`AGENTS_PLAN.md` build-order step 1)*

- Migrations + models: `agents`, `agent_versions`, `agent_sessions`, `agent_messages` → `Models/Agents/{Agent,AgentVersion,AgentSession,AgentMessage}.php`.
- `Actions/Agents/{CreateAgentSessionAction,SendAgentMessageAction}.php`, `Services/Agents/AgentRunner.php` (standalone-chat path only, no tools yet — a `Laravel\Ai` agent built from `Agent::instructions()`/`provider`/`model`).
- Internal API: `Http/Controllers/Api/Internal/V1/Agents/{AgentController,AgentSessionController}.php`, `routes/api/internal/agents.php` (append to `index.php`); Public API mirrors it (`Http/Controllers/Api/Public/V1/Agents/*`, `routes/api/public/agents.php`).
- Factories: `AgentFactory`, `AgentSessionFactory`.
- Feature test: send a message with no tools attached, assert an `agent_messages` row + a `runs` row (`runnable_type = AgentSession::class`) reach `completed`.

### Stage 7 — Tool binding (Agent ↔ Workflow/Node security boundary)
*(`AGENTS_PLAN.md` step 2 — the most load-bearing piece of this whole stage)*

- Migration + model: `agent_node` pivot (keyed on `node_type` string per `AGENTS_PLAN.md`'s reconciliation note, plus `custom_node_id` for `custom:{id}` types), `Agent::nodes(): BelongsToMany`.
- `Services/Agents/ToolRegistry.php` — merges pivot `config` over model-supplied tool-call arguments (bound fields always win), filters `configSchema()` down to `exposed_fields` for what the model is allowed to fill.
- `Ai/Tools/WorkflowTool.php` — a `Workflow` attached as a tool calls `StartWorkflowRunAction` (same as a `SubWorkflow` node).
- Feature test (must never regress, per `AGENTS_PLAN.md`): attach a connector node with a bound `channel`, assert a tool-call argument trying to override it is ignored.

### Stage 8 — Credits & billing ledger
*(`PLAN.md` Phase 7, pulled forward because Stages 3/6/7 already emit events that need it)*

- Migrations + models: `credit_transactions`, `usage_periods` → `Models/Billing/{CreditTransaction,UsagePeriod}.php` (`Plan`/`Subscription`/`CreditPack`/`ProcessedWebhookEvent` from `STRUCTURE.md` can follow once Stripe plan tiers are actually wired — not required to unblock Workflows/Agents).
- `Actions/Billing/DeductCreditsAction.php`, `Services/Billing/CreditMeter.php`.
- Fill in the Stage 3 `RecordRunCreditUsage` listener body + the Stage 7 tool-call metering (`source_type: node_run` vs `agent_step`, same ledger).
- `Exceptions/InsufficientCreditsException.php` mapped to 402.
- Feature test: assert a completed run's `node_runs` produce matching `credit_transactions` rows.

### Stage 9 — Agent embedded in workflow (`AgentNode`)
*(`AGENTS_PLAN.md` step 3, `NODES_CATALOG.md`'s `AgentNode` row)*

- `Nodes/AiAutomation/AgentNode.php` implementing `NodeContract`, calling `AgentRunner::ask()` (new synchronous entry point) with the run's templated prompt.
- Feature test: `{{ input.message }}` templating resolves into the agent prompt, output/usage land in the `node_runs` row.

### Stage 10 — Skills, Knowledge/RAG, Memory
*(`AGENTS_PLAN.md` steps 4-5)*

- Migrations + models: `skills`, `agent_skill` pivot, `document_embeddings`, `agent_memories` → `Models/Agents/{Skill,AgentMemory}.php`, `Models/Agents/DocumentEmbedding.php`.
- `Services/Agents/{SkillInjector,SearchKnowledgeTool}.php` (embeds via `Laravel\Ai\Embeddings`, cosine-similarity ranks in PHP per the schema note in `AGENTS_PLAN.md`).
- Internal API: `Http/Controllers/Api/Internal/V1/Skills/SkillController.php`, `routes/api/internal/skills.php`; Public mirrors read-only subset.
- Feature test: seed `document_embeddings`, assert top-ranked result matches closest-by-construction vector.

### Stage 11 — Node library expansion
*(`docs/NODES_CATALOG.md` — ongoing, one integration family per PR, not a blocker for anything above)*

- Priority 1 families first: Slack, Gmail/Sheets/Drive/Calendar, GitHub, Stripe — each new file under `Nodes/Integrations/{Service}/`, registered in `NodeRegistry`, no changes to files from earlier stages.
- This stage has no fixed end — expand as product needs dictate, per the priority ordering already in `NODES_CATALOG.md`.

## What's deliberately deferred (not part of this plan)

- Builder AI agent (`WorkflowBuilderSession`/`WorkflowBuilderAgent`, `AGENTS_PLAN.md` step 6) — needs `DryRunner`/`ContractGenerator` (`WORKFLOWS_PLAN.md` step 7), which needs a stable node set (Stage 11 in progress) to be worth building against.
- Evals (`AGENTS_PLAN.md` steps 7-8), Connectors/OAuth for integration nodes (`PLAN.md` Phase 6 — `ConnectorCredential` already partly covered by the existing `Models/Credentials/OAuthConnection.php`), Folders/Tags/StickyNotes (`WORKFLOWS_PLAN.md` step 8, cosmetic/organizational, safe to slot in any time after Stage 2).
- MCP transport (`PLAN.md` Phase 9) — trivial once `Actions/` exists per stage, deliberately last since it adds no new business logic.

## Full file structure

Every path below is a **new** file/folder this plan creates, grouped by stage number (matches the "Build order" section above). Nothing here replaces or deletes an existing path — existing `Trigger`/`Auth`/`Workspace`/`Billing`(Cashier) files are omitted since they're untouched. Route/seeder/`bootstrap`/`horizon.php` files marked "append" are edits to existing files (new lines only, no restructuring).

```
database/migrations/
  # Stage 1
  ..._create_workflows_table.php
  ..._create_workflow_versions_table.php
  ..._create_workflow_nodes_table.php
  ..._create_workflow_edges_table.php
  ..._create_runs_table.php
  ..._create_node_runs_table.php
  ..._create_node_categories_table.php
  ..._create_custom_nodes_table.php
  # Stage 4
  ..._create_workflow_approvals_table.php
  # Stage 6
  ..._create_agents_table.php
  ..._create_agent_versions_table.php
  ..._create_agent_sessions_table.php
  ..._create_agent_messages_table.php
  # Stage 7
  ..._create_agent_node_table.php
  # Stage 8
  ..._create_credit_transactions_table.php
  ..._create_usage_periods_table.php
  # Stage 10
  ..._create_skills_table.php
  ..._create_agent_skill_table.php
  ..._create_document_embeddings_table.php
  ..._create_agent_memories_table.php

database/factories/
  WorkflowFactory.php                          # Stage 1
  WorkflowVersionFactory.php                   # Stage 1
  RunFactory.php                               # Stage 1
  NodeRunFactory.php                           # Stage 1
  AgentFactory.php                        # Stage 6
  AgentSessionFactory.php                      # Stage 6

database/seeders/
  NodeCategoriesDataset.php                       # Stage 1
  DatabaseSeeder.php                           # append: call NodeCategoriesDataset

app/Enums/
  PortType.php                                 # Stage 1
  NodeCategory.php                              # Stage 1
  RunStatus.php                                # Stage 1
  NodeRunStatus.php                             # Stage 1
  Queue.php                                     # Stage 1 (add cases as later stages need them)

app/Contracts/
  NodeContract.php                              # Stage 1

app/Models/
  Workflows/
    Workflow.php                                # Stage 1
    WorkflowVersion.php                         # Stage 1
    WorkflowNode.php                             # Stage 1
    WorkflowEdge.php                             # Stage 1
    WorkflowApproval.php                         # Stage 4
  Runs/
    Run.php                                      # Stage 1
    NodeRun.php                                  # Stage 1
  Nodes/
    NodeCategory.php                             # Stage 1
    CustomNode.php                               # Stage 1
  Agents/
    Agent.php                               # Stage 6
    AgentVersion.php                        # Stage 6
    AgentSession.php                              # Stage 6
    AgentMessage.php                              # Stage 6
    Skill.php                                     # Stage 10
    DocumentEmbedding.php                         # Stage 10
    AgentMemory.php                               # Stage 10
  Billing/
    CreditTransaction.php                         # Stage 8
    UsagePeriod.php                               # Stage 8

app/Services/
  Workflows/
    NodeRegistry.php                              # Stage 1
    ConfigSchemaValidator.php                     # Stage 2
    GraphValidator.php                            # Stage 2
    WorkflowRunner.php                            # Stage 3
    StepOptions.php                               # Stage 3
    TemplateResolver.php                          # Stage 5
    ExpressionEvaluator.php                       # Stage 5
    SafePattern.php                               # Stage 5
    TemplatePaths.php                             # Stage 5
    Engine/
      GraphAdvancer.php                           # Stage 3
      StepFailureHandler.php                      # Stage 3
      LoopCoordinator.php                          # Stage 4
      SubWorkflowCoordinator.php                   # Stage 4
  Agents/
    AgentRunner.php                                # Stage 6 (standalone), Stage 9 (ask())
    ToolRegistry.php                               # Stage 7
    SkillInjector.php                              # Stage 10
    SearchKnowledgeTool.php                        # Stage 10
  Billing/
    CreditMeter.php                                # Stage 8

app/Actions/
  Workflows/
    StartWorkflowRunAction.php                     # Stage 3
  Agents/
    CreateAgentSessionAction.php                   # Stage 6
    SendAgentMessageAction.php                     # Stage 6
  Billing/
    DeductCreditsAction.php                        # Stage 8

app/Ai/
  Tools/
    WorkflowTool.php                               # Stage 7

app/Nodes/
  DataTransform/
    TransformNode.php                              # Stage 1
    CallApiNode.php                                # Stage 1
    RunCodeNode.php                                # Stage 1
  FlowLogic/
    RouterNode.php                                 # Stage 1
    FilterNode.php                                 # Stage 1
    LoopNode.php                                   # Stage 4
    SubWorkflowNode.php                            # Stage 4
    HumanApprovalNode.php                          # Stage 4
    WaitNode.php                                   # Stage 4
    JoinPathsNode.php                              # Stage 4
    DelayNode.php                                  # Stage 4
  AiAutomation/
    AskAiNode.php                                  # Stage 1
    AgentNode.php                                  # Stage 9
  Integrations/                                    # Stage 11, one family per PR
    Slack/*.php
    Gmail/*.php
    GoogleSheets/*.php
    GoogleDrive/*.php
    GoogleCalendar/*.php
    GitHub/*.php
    Stripe/*.php
    ...

app/Jobs/
  Workflows/
    ExecuteNodeJob.php                             # Stage 3
    DispatchNextNodesJob.php                        # Stage 3
    ResumeWorkflowJob.php                           # Stage 3
  System/
    ExpireStaleWaitsJob.php                         # Stage 4

app/Events/
  Runs/
    RunCompleted.php                               # Stage 3
    RunFailed.php                                   # Stage 3

app/Listeners/
  Workflows/
    RecordRunCreditUsage.php                        # Stage 3 (stub) / Stage 8 (filled in)
    NotifyOnRunFailure.php                           # Stage 3

app/Exceptions/
  WorkflowValidationException.php                    # Stage 2
  InsufficientCreditsException.php                   # Stage 8

app/Providers/
  NodeRegistryServiceProvider.php                    # Stage 1

app/Http/Controllers/Api/
  Internal/V1/
    Workflows/
      WorkflowController.php                         # Stage 2
      WorkflowVersionController.php                  # Stage 2
      WorkflowBuilderController.php                  # Stage 2
    Runs/
      RunController.php                              # Stage 3
    Agents/
      AgentController.php                            # Stage 6
      AgentSessionController.php                     # Stage 6
    Skills/
      SkillController.php                            # Stage 10
  Public/V1/
    Workflows/
      WorkflowController.php                         # Stage 3
    Runs/
      RunController.php                               # Stage 3
    Agents/
      AgentController.php                              # Stage 6
      AgentSessionController.php                       # Stage 6
    Skills/
      SkillController.php                               # Stage 10

app/Http/Requests/Api/Internal/V1/
  Workflows/
    StoreWorkflowRequest.php                          # Stage 2
    UpdateWorkflowRequest.php                          # Stage 2

app/Http/Resources/
  Internal/
    WorkflowResource.php                               # Stage 2
    RunResource.php                                     # Stage 3
    AgentResource.php                                   # Stage 6
  Public/V1/
    WorkflowResource.php                                 # Stage 3
    RunResource.php                                       # Stage 3
    AgentResource.php                                      # Stage 6

routes/api/internal/
  workflows.php                                          # Stage 2 (append require to index.php)
  workflow_builder.php                                    # Stage 2 (append require to index.php)
  runs.php                                                 # Stage 3 (append require to index.php)
  agents.php                                                # Stage 6 (append require to index.php)
  skills.php                                                 # Stage 10 (append require to index.php)

routes/api/public/
  index.php                                                 # Stage 3 (new — wires public surface if not already present)
  workflows.php                                              # Stage 3
  runs.php                                                    # Stage 3
  agents.php                                                   # Stage 6
  skills.php                                                    # Stage 10

config/horizon.php                                              # append: supervisor-workflows-dispatch/execute (Stage 3), supervisor-ai-agent (Stage 6)
bootstrap/app.php                                                 # append: WorkflowValidationException/InsufficientCreditsException exception mappings
routes/console.php                                                 # append: ExpireStaleWaitsJob schedule (Stage 4)

tests/Feature/Api/Internal/Workflows/                                # Stage 2-5
tests/Feature/Api/Public/Workflows/                                   # Stage 3
tests/Feature/Api/{Internal,Public}/Agents/                            # Stage 6-9
tests/Feature/Agents/                                                    # Stage 7 (tool-binding security test), Stage 9-10
tests/Unit/Services/Workflows/                                            # Stage 2-5
tests/Unit/Services/Agents/                                                 # Stage 7, 10
```

## Verification summary

Each stage above lists its own feature test(s); run the full suite per stage before moving to the next (`php artisan test --compact --filter=<Stage keyword>`). Stages 1-5 (Workflows) can be built and merged independently of 6-10 (Agents) up to the point Stage 7 needs Stage 3's engine and Stage 9 needs both — so a two-track build (one PR series per track) is viable if there are two people working in parallel, converging at Stage 7.
