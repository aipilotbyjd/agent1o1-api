# Gumloop-style Workflow/Agent Platform — Backend API Plan

## Context

The goal is a SaaS backend (API only — frontend/canvas is out of scope for this plan) that lets users build and run AI-powered automations, modeled on Gumloop: a **node-based workflow engine** plus an **autonomous Agent layer**, both callable via a REST API. No specific industry vertical is being targeted — this is a general-purpose builder, differentiated later on product/UX/pricing rather than domain.

Stack decisions already made:
- **Backend**: Laravel
- **Agents**: Laravel AI package ("Laravel agents") for LLM orchestration/tool-calling
- **Execution**: Laravel queues + jobs (Redis/database queue, Horizon for monitoring) — no separate execution service
- **Frontend**: React (canvas/editor) — explicitly not planned here; this plan only covers the API/backend

Research basis (from Gumloop's docs): workflows are DAGs of typed nodes (Text / List of Text / List of List of Text, with explicit conversion nodes and a "Loop Mode" for batch/list processing); Subflows let a whole workflow be reused as a node elsewhere; Agents are a separate, conversational, tool-driven layer that can itself call workflows or be embedded as a node; credits meter usage (1 base credit/run, AI nodes billed by tokens, some integration nodes have fixed costs, BYOK halves AI credit cost); triggers include manual/schedule/webhook/app-event; the platform exposes Agents, Flows, Runs, Sessions, Files/Artifacts, Skills, MCP servers, Models, and Teams via a REST API + SDKs.

The plan is phased so each phase ships something runnable end-to-end via the API, rather than building all subsystems in parallel. Naming and folder layout follow `STRUCTURE.md` — entities are `Workflow`/`Run`/etc. (not `Flow`), and the API itself is split into two surfaces from Phase 1 onward (see "Multi-tenancy, auth, API" below).

## Architecture Overview

**Core entities** (Laravel Eloquent models, all scoped to a `Workspace` for multi-tenancy — see `STRUCTURE.md`'s `Models/` tree):

- `Workspace` — tenant boundary directly (no separate `Organization` wrapper); `User` belongs to Workspaces via a pivot with `role`. Uses Cashier's `Billable` trait itself.
- `ApiKey` — workspace-scoped, hashed, rate-limited-by-plan credential for the Public API (see below); managed by workspace members via the Internal API.
- `Workflow` — a saved workflow (DAG). Has versions (`WorkflowVersion`) so edits don't break running instances.
- `Node` (definition, code-level, not DB) — a class implementing a common `NodeContract` (inputs, params, outputs, `execute()`). `WorkflowNode` (DB) — an instance of a node type placed on a specific workflow version, with its configured params and position metadata.
- `WorkflowEdge` — connection between two `WorkflowNode` ports; carries the type-compatibility contract.
- `Run` — one execution of a `Workflow` or an `AgentSession` (polymorphic `runnable_type`/`runnable_id`); has status, trigger source, input payload.
- `NodeRun` — one execution of a `WorkflowNode` within a `Run`; has status, input/output payload, credit cost, error info. This is the "Run Log" granularity.
- `Trigger` — a materialized record derived from a trigger-type `WorkflowNode`'s params (`ManualTriggerNode`, `ScheduleTriggerNode` w/ cron expr, `WebhookTriggerNode` w/ unique URL+secret, app-event triggers tied to a `Connector`) — kept in sync so the scheduler/webhook router can query it without scanning JSON params on every `WorkflowNode`.
- Subflow reference — just a `WorkflowNode` whose node type is `subflow` and whose param points at another `Workflow` id; no separate table needed.
- `Agent` — config: model, system instructions, attached tools (Connectors + Workflows + Skills), triggers.
- `AgentSession` — a conversation thread with an Agent; `AgentMessage` for turns; tool-call steps logged similarly to `NodeRun` for credit metering.
- `Skill` — reusable instruction/knowledge snippet an Agent can use; versioned.
- `Connector` — an integration definition (Slack, Gmail, generic HTTP, etc.) plus per-workspace `ConnectorCredential` (OAuth tokens or API keys, encrypted).
- `CreditTransaction` / `UsagePeriod` — append-only usage ledger (workspace, source type [`node_run`/`agent_step`], source id, credits consumed, reason), rolled up per billing period. Written by `Actions/Billing/DeductCreditsAction` via `Services/Billing/CreditMeter`.
- `Artifact` — file produced/consumed by a run, stored via Laravel filesystem (S3-compatible), with `Download` endpoints.

**Business logic lives in `Actions/`, not controllers**: every mutating use case (`StartWorkflowRunAction`, `SendAgentMessageAction`, `DeductCreditsAction`, ...) is a single-purpose class called from three places — the Public API controllers, the Internal API controllers, and the MCP tools (`Mcp/Tools/*`) — so "what does starting a run actually do" has one implementation, not three.

**Execution engine**:
- A `Run` is created (via `StartWorkflowRunAction`, `runnable_type = Workflow::class`), then a `DispatchNextNodesJob` resolves the DAG (topological order using `WorkflowEdge`), and dispatches one queued job per ready `WorkflowNode` (`ExecuteNodeJob`). Each job creates a `NodeRun`, calls the node's `execute()`, writes outputs, records credit cost via `CreditTransaction`, and on success dispatches downstream nodes whose inputs are now fully satisfied. On completion/failure, a `RunCompleted`/`RunFailed` event fires, decoupling side effects (credit deduction, failure notifications) into `Listeners/` rather than hardcoding them into the job.
- **Loop Mode / batch**: when a `WorkflowEdge`'s target expects a singular item but the source produced a list, the node is fanned out into N `NodeRun`s (one per list item) as separate queued jobs, then re-joined by a "Join" node — mirrors Gumloop's Loop Mode without needing a different execution model.
- **Type checking**: validated at workflow-save time (not just at run time) — compare each `WorkflowEdge`'s source/target port types against a small compatibility table (Text, List<Text>, List<List<Text>>, File, Boolean, Number), reject invalid graphs before they can run.
- **Human-in-the-loop / checkpoints**: a node can mark its `NodeRun` as `awaiting_input`; the `Run` pauses; a separate API endpoint resumes it by supplying the missing input, re-dispatching from that node. This also naturally gives "Workflow Checkpoints" (resumable runs) since state lives in `NodeRun` outputs, not in-memory.
- **Error handling**: an `ErrorShieldNode` catches failures from upstream and routes to an alternate path instead of failing the whole `Run`; standard nodes retry via Laravel's queue retry/backoff, with max-attempts before marking `NodeRun` failed and propagating failure to the `Run`. Domain-level failures (e.g. insufficient credits before a run can even start) raise typed `Exceptions/` classes mapped centrally to HTTP status codes, so both API surfaces return consistent error shapes.

**Queues & Horizon**: every job's queue is a typed `Queue` enum case (`workflows.dispatch`, `workflows.execute`, `ai.agent`, `triggers.poll`, `triggers.event`, `billing.webhook`, `system.maintenance`, `system.notification`), never a raw string. A dedicated Horizon supervisor per queue keeps unrelated workloads from starving each other — most importantly, `ai.agent` gets its own low `maxProcesses` ceiling (independent of `workflows.execute`'s much higher one) to stay under LLM provider rate limits, and `billing.webhook` gets the highest OS-level priority (`nice`) since it's money-adjacent and must never lag behind workflow load. Full supervisor config and the queue-to-job mapping are in `STRUCTURE.md`.

**Agent layer**:
- Built on the Laravel AI package's agent/tool primitives. An `Agent`'s tools are a uniform list: Connector actions, `Workflow`s (registered as callable tools with their Input/Output schema), and `Skill`s (injected as context/instructions).
- Agent turn loop (`SendAgentMessageAction` → `Services/Agents/AgentRunner`): message in → Laravel AI package handles model call + tool selection → each tool call is logged as a step (credit metering same ledger as `NodeRun`) → loop until final response. If a tool call is "run this Workflow," it calls `StartWorkflowRunAction` directly — synchronously (blocking the agent step) or async with a webhook-style callback for long-running workflows.
- An Agent can be embedded inside a `Workflow` via an `agent` node type (calls `AgentSession` programmatically), matching Gumloop's "Agent node."

**Multi-tenancy, auth, API** — two surfaces, one shared logic layer (full detail in `STRUCTURE.md`'s "Public vs. Internal API" section):
- **`Api/Internal`** (`/api/internal`, unversioned) — the API your own React canvas talks to. Auth via Laravel Sanctum session/cookie, workspace resolved from the session via `EnsureWorkspaceScope`. Free to be chatty/UI-shaped; owns editor-only concerns (`WorkflowBuilderController` for node placement/edges/autosave) that the Public surface never exposes.
- **`Api/Public/V1`** (`/api/v1`, versioned) — external developers and this platform's own MCP server. Auth via workspace-scoped `ApiKey` (hashed, rate-limited by plan) through `EnsureApiKeyIsValid`. Deliberately narrower: run/list/inspect (`Workflows`, `Runs`, `Agents`, `Files/Artifacts`, `Skills`), not workflow-editing internals — this is the contract external integrators and MCP tools depend on staying stable.
- Authorization: Laravel Policies per resource (Workflow, Agent, Connector), shared by both surfaces since "can this actor do X" doesn't depend on transport — only how the actor was resolved (session user vs. `ApiKey` → workspace) differs upstream. A `role` on the workspace-user pivot (owner/admin/member) is the v1 permission model — custom roles/SCIM deferred to the enterprise phase.
- REST surface mirrors Gumloop's documented API groups so the shape is proven: `Workflows` (CRUD, list, retrieve input schema), `Runs` (start, retrieve, kill, history), `Agents` (CRUD, sessions, messages), `Files/Artifacts` (upload/download, single + multi), `Skills` (CRUD), `Models` (list available), `Teams`/`Workspace` (list/manage users) — Internal exposes all of these plus editor/builder endpoints; Public exposes the run/list/inspect subset.

## Phased Delivery

Phases 2–9 below are now specified in full, detail-level plan docs, ported from the old project (`agent-1o1-api`)'s working implementation of this same system — see `docs/WORKFLOWS_PLAN.md`, `docs/NODES_CATALOG.md`, `docs/AGENTS_PLAN.md`, and `docs/TRIGGERS_PLAN.md` for schema, service/engine design, build order, and verification per phase. The summary below is kept as the top-level index; the linked docs are authoritative on detail.

1. **Foundations** — Workspace/User/ApiKey models, Sanctum auth (Internal), API-key auth (Public), Policies, the `Actions/` layer scaffolding, base middleware (`EnsureWorkspaceScope`, `EnsureApiKeyIsValid`, rate limiting), the two route surfaces (`routes/api/internal/`, `routes/api/public/`) wired up empty but working end-to-end (auth round-trips on both). **Done** — see `docs/AUTH_PLAN.md`, `docs/WORKSPACE_PLAN.md`.
2. **Core workflow engine** — see `docs/WORKFLOWS_PLAN.md` in full: Workflow/WorkflowVersion/WorkflowNode/WorkflowEdge/Run/NodeRun models, `NodeContract` + `NodeRegistry`, `GraphValidator`, `StartWorkflowRunAction` + DAG execution (`GraphAdvancer`/`StepFailureHandler`), Loop Mode (`LoopCoordinator`), Subflows (`SubWorkflowCoordinator`), human-in-the-loop approvals/waits, templating engine.
3. **Node library** — see `docs/NODES_CATALOG.md`: full ~150-node catalog ported from the old project's `Nodes/Apps/*`, grouped by priority (Slack/Google/GitHub/Stripe/AWS S3 first) for incremental delivery, one integration family per PR.
4. **Triggers + monitoring** — see `docs/TRIGGERS_PLAN.md`: webhook/schedule/polling/manual triggers, the durable-inbox `TriggerEvent` pipeline, signature verification, circuit breaker, retry-stuck recovery — ported near-verbatim from the old project's own dedicated trigger plan, including its "design decisions worth keeping" table.
5. **Agent layer** — see `docs/AGENTS_PLAN.md`: Agent/AgentSession/AgentMessage, `Services/Agents/AgentRunner`, the bound-config/exposed-fields tool-attachment security model, Skill/knowledge base (searched or always-included collections)/AgentMemory, Agent node type for embedding in Workflows, the chat-based `WorkflowBuilderAgent`, and agent evals.
6. **Connectors/integrations** — Connector abstraction + ConnectorCredential (encrypted OAuth/API-key storage, folding in the already-built `OAuthConnection`), OAuth2 flow endpoints; unlocks the node library in `docs/NODES_CATALOG.md` and the trigger preset catalog in `docs/TRIGGERS_PLAN.md`.
7. **Credits & billing** — CreditTransaction/UsagePeriod + per-node/per-agent-step cost table (`DeductCreditsAction`/`CreditMeter`), usage endpoints, 75%/90% threshold alerts, Stripe subscription integration for plan tiers (Cashier), BYOK support (store user's own model API key, halve AI credit cost).
8. **Contract testing, environments/releases, sharing/templates, git sync** — workflow-lifecycle/collaboration features the old project has; lighter-detail until scheduled, expand into a dedicated doc when this phase starts.
9. **MCP + Brain (stretch)** — `Mcp/Server.php` + `Mcp/Tools/*` calling straight into the `Actions/` layer already built in Phases 2–7 (no new business logic, just a third transport); consume external MCP servers as Agent tools; a minimal Brain (workspace knowledge base with embeddings + similarity search) as an Agent context source — the `document_embeddings`/`SearchKnowledgeTool` design in `docs/AGENTS_PLAN.md` is the same mechanism, scoped per-agent rather than workspace-wide.
10. **Enterprise (deferred)** — SSO/SAML/SCIM, custom roles, audit logs, static egress IPs, usage data export. Stub only if/when a customer needs it.

## Verification

Since this is a backend API with no UI yet, each phase should be verified via automated feature tests (Laravel's Pest/PHPUnit, split under `tests/Feature/Api/Public` and `tests/Feature/Api/Internal` per `STRUCTURE.md`) plus manual `curl`/Postman hits against both surfaces:
- Phase 1: a test that an Internal request without a Sanctum session is rejected, a Public request without a valid `ApiKey` is rejected, and a valid request on each surface resolves to the correct workspace.
- Phase 2: a test that builds a simple Workflow (Input → Ask AI → Output) via the Internal API, starts a run via both the Internal and Public API, and asserts the Run/NodeRun records reach `completed` with expected output on both.
- Phase 3: a test that creates a WebhookTrigger, posts to its URL, and asserts a `Run` was created and executed.
- Phase 4: a test with a list input into a Loop Mode node asserting N NodeRuns are created and correctly joined.
- Phase 5: a test that sends an Agent a message requiring a tool call to a registered Workflow, asserting the Workflow actually ran (via `SendAgentMessageAction` → `StartWorkflowRunAction`) and the credit ledger recorded both the agent step and the workflow's node runs.
- Phase 6: an OAuth connector test using a mocked provider, and an app-event trigger firing a Workflow from a simulated webhook event.
- Phase 7: an assertion that credits are deducted correctly per the cost table, and that a Stripe test-mode subscription upgrades the workspace's plan/limits.
- Phase 8: an MCP tool test (`tests/Feature/Mcp/RunWorkflowToolTest.php`) asserting `RunWorkflowTool` calls `StartWorkflowRunAction` and returns the same run state the Public API would.
