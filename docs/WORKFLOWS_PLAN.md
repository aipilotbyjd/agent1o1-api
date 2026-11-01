# Workflow Engine Plan

## Context

`docs/STRUCTURE.md` names the target entities (`Workflow`/`WorkflowVersion`/`WorkflowNode`/`WorkflowEdge`/`Run`/`NodeRun`) but nothing under `Models/Workflows`, `Services/Workflows`, or `Nodes/` exists yet in this project. The old project (`agent-1o1-api`) already built and ran this exact engine — a DAG of typed steps, versioned publishing, retries/backoff, loop fan-out as real child runs, sub-workflows, human approval, and long-running callback waits — and this doc ports it, renamed to this project's committed naming. See `docs/PLAN.md`'s Phase 1 (this doc) for where this sits in the overall build order, and `docs/NODES_CATALOG.md` for the node library that plugs into `NodeContract` below.

Old-project names differ (`WorkflowStep`→`WorkflowNode`, `WorkflowStepEdge`→`WorkflowEdge`, `RunStep`→`NodeRun`, `Node` (catalog row)→`NodeCategory` + PHP classes) — the old project's shared, polymorphic `Run` model is kept as `Run` unchanged (see `runs` table below) — every section below states the old file read for behavior and the new name/path to build.

## Schema

### `workflows`
Ported from `create_workflows_table` (old project, already read):
```
id, workspace_id (FK cascade), folder_id (FK, nullable), name, slug, description (nullable),
status (default 'draft'), current_version_id (FK workflow_versions, added after that table exists, nullOnDelete),
has_unpublished_changes (bool, default false), created_by (FK users, nullOnDelete),
timestamps, softDeletes
unique(workspace_id, slug)
```

### `workflow_versions`
Ported from `create_workflow_versions_table`:
```
id, workflow_id (FK cascade), version (unsignedInteger), graph (json — {nodes: [...], edges: [...]}),
notes (string 500, nullable), published_by (FK users, nullOnDelete), timestamps
unique(workflow_id, version)
```
`graph` is the full snapshot at publish time — a workflow `Run` pins to a `workflow_version_id`, so editing the live draft never changes what an in-flight run executes.

### `workflow_nodes` (was `workflow_steps`)
```
id, workflow_id (FK cascade), key (string — stable id used in graph/edges/templating), type (string),
config (json, nullable), position (json, nullable — canvas x/y), timestamps
unique(workflow_id, key)
```
`node_id` (FK to the catalog `nodes`/`NodeCategory`-adjacent table, per `STRUCTURE.md`'s `CustomNode` split — see Node Registry below) is resolved and stored the same way `Workflow::replaceGraph()` does it in the old project (already read): a lookup by `type` against built-in-or-workspace-scoped catalog rows, keyed once per `replaceGraph()` call rather than per node.

### `workflow_edges` (was `workflow_step_edges`)
```
id, workflow_id (FK cascade), from_node_id (FK workflow_nodes cascade), to_node_id (FK workflow_nodes cascade),
condition (string, nullable), timestamps
unique(from_node_id, to_node_id, condition)
```
`condition` is what makes branching work: `null` = unconditional, a literal value = "follow only if the source's `result` output equals this", and the reserved value `'error'` = "follow only if the source step failed" (see `GraphAdvancer::ERROR_CONDITION` below).

### `runs` (was `workflow_runs`; reverted to old project's shared, polymorphic `Run` model)
```
id, workspace_id (FK cascade), runnable_type (string), runnable_id (unsignedBigInteger — Workflow or AgentSession),
workflow_id (FK cascade, nullable — set only when runnable_type is Workflow), workflow_version_id (FK nullOnDelete, nullable),
parent_run_id (FK runs, nullOnDelete — sub-workflow/loop child), parent_node_id (FK node_runs, nullOnDelete),
loop_index (unsignedInteger, nullable — which loop iteration this child is), environment_id (nullable, Phase 9),
status (default 'pending'), trigger_type (default 'manual'), input (json, nullable), output (json, nullable),
error (text, nullable), triggered_by (FK users, nullOnDelete), started_at, finished_at, timestamps
index(workspace_id, status), index(workspace_id, created_at), index(parent_node_id, loop_index), index(runnable_type, runnable_id)
```
**Reverses the earlier divergence noted in `AGENTS_PLAN.md`**: instead of a workflow-only `workflow_runs` plus a separate `agent_sessions`/`agent_messages` pair with no run record at all, there is one `runs` table again — `runnable_type`/`runnable_id` point at either a `Workflow` (`StartWorkflowRunAction` creates the row, `workflow_id`/`workflow_version_id` populated, `NodeRun`s attach to it) or an `AgentSession` (`AgentRunner` creates one `runs` row per session/turn, workflow-only columns stay null). This is why `$workspace->runs()` in `STRUCTURE.md` and the single `runs.php` route file make sense as one list — a workspace's "Runs" tab is workflow executions and agent invocations together, not two APIs to merge client-side. Per-turn agent chat detail (`role`, `content`, `tool_calls`) still lives in `agent_messages`, linked to the `runs` row via `AgentSession`, exactly as `NodeRun` still holds per-node detail under a workflow's `runs` row.

### `node_runs` (was `run_steps`)
```
id, run_id (FK runs cascade), key, type, status (default 'pending'),
input (json, nullable), output (json, nullable), error (text, nullable), usage (json, nullable — token/credit accounting),
attempt (unsignedInteger, default 1), max_attempts (unsignedInteger, default 1), retry_delay_seconds (unsignedInteger, default 0),
started_at, finished_at,
callback_token (string, nullable, unique), callback_expires_at (nullable, indexed),
timestamps
unique(run_id, key), index(run_id, status)
```

### `node_categories` + `custom_nodes`
`node_categories` ported as-is from old `create_node_categories_table` (`name`, `slug` unique, `description`, `icon`, `color`, `sort_order`, `kind` — `core`|`app`) — this is the product-facing taxonomy `STRUCTURE.md` already documents. Old project's single `nodes` table (catalog rows for both built-ins *and* per-workspace custom nodes, `is_custom` flag) is **split** per `STRUCTURE.md`'s existing design: built-ins are PHP `NodeContract` classes (no DB row needed beyond the `type` string), and only user-defined nodes get a `custom_nodes` row:
```
custom_nodes: id, workspace_id (FK cascade), category_id (FK node_categories), type (string, unique per workspace),
name, description, icon, color, config_schema (json), input_schema, output_schema (nullable json),
credential_type (nullable), is_active (bool, default true), created_by, timestamps
unique(workspace_id, type)
```

### `folders`, `tags` (+ pivot), `sticky_notes`, `workflow_approvals`
Ported straight from old migrations — no design changes:
- `folders`: `id, workspace_id, parent_id (nullable, self-ref), name, timestamps`
- `tags` + `taggables` pivot (`tag_id`, `taggable_type`, `taggable_id`) for `Workflow` (and later `Agent`) tagging
- `sticky_notes`: `id, workflow_id, content, position (json), color, timestamps` — canvas annotations, no execution relevance
- `workflow_approvals`: `id, run_id, node_run_id, requested_at, decided_at (nullable), decided_by (nullable FK users), decision (nullable), message, timestamps` — backs the `HumanApproval` node type (see below)

## Node contract & registry

Ported from `Services/Workflows/Nodes/{ExecutableNode,NodeRegistry,NodeDefinition,NodeResolver,StepNodeResolver,ConfigSchemaValidator}.php` (all already read/surveyed):

- **`Contracts/NodeContract.php`** (old: `ExecutableNode`) — the interface connector/AI/data nodes implement:
  ```php
  interface NodeContract {
      public function execute(Run $run, array $config, array $context): array; // returns step output
      public function configSchema(): array; // JSON-schema-shaped, used at save+publish time
      public function category(): string; // must match a NodeCategory.slug
  }
  ```
  Flow-control node types (`Condition`/`Router`, `Merge`, `Delay`, `Loop`, `HumanApproval`, `SubWorkflow`, `Wait`) deliberately do **not** implement `NodeContract` — the engine drives their behavior directly (graph traversal, not "call out to something"), exactly as the old project's doc comment on `ExecutableNode` states.
- **`Services/Workflows/NodeRegistry.php`** — binds every built-in `Nodes/*` class by its `type` string at boot (via a `NodeRegistryServiceProvider`, per `STRUCTURE.md`), and resolves `custom:{id}` type strings to a `CustomNode` row instead. `NodeRegistry::connectors()` (nodes implementing `NodeContract`) is what a `tool`-type step, and later an Agent's tool list, can be pointed at — same filter old project's `NodeRegistry::connectors()` uses.
- **`Services/Workflows/Nodes/ConfigSchemaValidator.php`** — validates a step's `config` against its node's `configSchema()`; called from both `Workflow::replaceGraph()` (draft save) and `GraphValidator` (publish) — ported as-is.

## Execution engine

Ported from `Services/Workflows/{WorkflowRunner.php, WorkflowGraph.php, StepOptions.php}` + `Services/Workflows/Engine/{RunStarter,GraphAdvancer,StepContextBuilder,RunLogger,StepFailureHandler,LoopCoordinator,SubWorkflowCoordinator}.php` (all already read in full). Renamed per the table in the top-level plan; behavior is a direct port.

- **`Actions/Workflows/StartWorkflowRunAction`** (old: `RunStarter`) — creates a `runs` row (`runnable_type = Workflow::class`) pinned to the workflow's `current_version_id` (or a specific version for replay/testing), sets `status = pending`, dispatches the entry node(s).
- **`Services/Workflows/WorkflowRunner`** — the engine's single entry point (`executeStep`, `retryStep`, `resolveApproval`, `resolveCallback`, `expireWait`, `resolveSubWorkflow`), exactly mirroring old project's class of the same name. Callers (jobs, controllers, listeners) depend only on this, not on its collaborators. Key behaviors to preserve verbatim:
  - **Idempotent step creation**: `NodeRun::create()` is wrapped in a `UniqueConstraintViolationException` catch — two branches racing to dispatch the same node both attempt the insert, the unique `(run_id, key)` index makes the loser a no-op instead of a duplicate. This is what makes `GraphAdvancer::advance()` safe to call from multiple concurrent branches.
  - **Merge nodes wait, not block**: a `Merge` node only actually runs once every incoming branch has settled (`GraphAdvancer::allComplete()`); every branch but the last just returns.
  - **Timeout enforcement is post-hoc**: a node whose handler overran its configured timeout is marked failed *after* the call returns — it does not interrupt in-flight work (that's the queue worker's own job timeout), but it stops the graph advancing past a late result.
  - **`HumanApproval`, `SubWorkflow`, `Wait`, and `Loop(foreach)`** node types pause the run rather than running through a handler — they hand off to `requestApproval()`/`SubWorkflowCoordinator::start()`/`startWait()`/`LoopCoordinator::start()` respectively and return without advancing.
- **`Services/Workflows/Engine/GraphAdvancer`** — the traversal engine, unchanged design:
  - `advance()` evaluates every outgoing edge from the just-settled node; edges whose condition doesn't match the outcome get their target subtree marked **skipped** (recursively, `skipUnreachable()`) *before* the matching edges dispatch — this ordering matters because a downstream `Merge` needs to see "this branch will never arrive" before it can join.
  - `edgeMatches()`: an `error`-condition edge fires only on failure; a normal edge fires only on success and only if its condition (if any) equals the node's `result` output. A failed node never falls through to an unconditional edge.
  - `finishIfDone()` completes the run under `lockForUpdate()` — two branches finishing simultaneously must not both observe "nothing in flight" and race to complete a run whose sibling node is about to be created.
  - `ERROR_CONDITION = 'error'` constant, `hasErrorEdge()` — used by `StepFailureHandler` to decide "retry vs. route down error edge vs. fail the run."
- **`Services/Workflows/Engine/StepFailureHandler`** — retry-vs-route-vs-fail policy, ported as-is: exponential backoff with ±25% jitter (`min(base * 2^(attempt-1), 3600)`), redacts credentials from failure messages before persisting (`RunLogger::redact()`), and only routes down an `error` edge or fails the whole run once retries are exhausted.
- **`Services/Workflows/Engine/LoopCoordinator`** — Loop Mode, implemented as **real child `runs` rows**, not an in-handler batch: `items` path resolves to a list from context, one child run per item up to `max_concurrent`, subsequent items released as earlier ones settle (`join()`), `on_item_error` policy (`fail_fast`|`continue`|`collect_errors`) controls whether one bad item fails the whole loop. This is exactly Gumloop's "Loop Mode" from `docs/PLAN.md`'s research notes, and confirms that design was already validated in the old project.
- **`Services/Workflows/Engine/SubWorkflowCoordinator`** — `subflow` node type: starts a child `run` of another workflow, pauses the parent node, resumes it (or fails it) when the child settles. Also owns the resume path loop children use, since "which parent does this finished child belong to" is one piece of logic regardless of whether the child came from a `SubWorkflow` or `Loop` node.
- **`Services/Workflows/{TemplateResolver,ExpressionEvaluator,SafePattern,TemplatePaths}`** — the `{{node.output}}` templating engine nodes/edges use to reference upstream output; self-contained, ported as-is (behavior not yet re-read in detail — read at build time, no design risk).
- **`Services/Workflows/StepOptions`** — per-node `max_attempts`/`retry_delay_seconds`/`timeout_seconds`/`continue_on_error`, parsed once from a node's config (`StepOptions::fromStep()`), ported as-is.

## Validation (`Services/Workflows/GraphValidator`)

Ported as-is (already read in full) — runs at both draft-save time (`Workflow::replaceGraph()`, node-config-schema issues only) and publish time (`Workflow::publishVersion()`, full graph validation). Checks, in order (later checks skipped if earlier ones fail, since a dangling-edge graph makes cycle/reachability checks meaningless):
1. Duplicate node keys
2. Dangling edges (edge endpoint doesn't reference a real node)
3. Cycles (DFS with visiting/visited coloring)
4. No entry node (every node has an incoming edge)
5. Unreachable nodes (BFS from entry nodes)
6. Per-node config schema issues (delegates to `ConfigSchemaValidator`)

This is what makes "an invalid graph is a compile error, not a run that hangs" true — `docs/PLAN.md`'s existing "Type checking" bullet already commits to this; this is the concrete mechanism.

## Human-in-the-loop, waits, and dry runs

- **`HumanApproval`** node type + `workflow_approvals` table: pauses the run, notifies workspace owners/admins (`Services/Notifications`, ported from old `RunApprovalRequestedNotification`), resumes via `WorkflowRunner::resolveApproval()` on accept/reject.
- **`Wait`** node type: hands out a one-time `callback_token` + `callback_expires_at`, parks the run; resumed via `resolveCallback()` (token consumed, single-use) or expired via `expireWait()` (fails the node unless `continue_on_timeout` is set, in which case it completes with `timed_out: true`). This is `docs/PLAN.md`'s "Workflow Checkpoints" bullet, concretely — state lives in `NodeRun` rows, not in-memory, so a resumed run picks up exactly where old-project's design already proved out.
- **`DryRunner`** (`Services/Workflows/DryRunner.php`, not yet read in detail) — executes a graph without persisting a real `runs` row, used by the builder AI agent (`docs/AGENTS_PLAN.md` §6) to let a user/agent test a workflow-in-progress.
- **`ContractGenerator`** (`Services/Workflows/ContractGenerator.php`, not yet read in detail) — derives an input/output JSON-schema "contract" for a workflow, feeding the deferred contract-testing phase (`docs/PLAN.md` Phase 9).

## Build order

1. Models + migrations above (`workflows`, `workflow_versions`, `workflow_nodes`, `workflow_edges`, `runs`, `node_runs`, `node_categories`, `custom_nodes`) + `NodeCategoriesDataset` (core categories only — `flow-logic`, `data-transform`, `ai-automation`, `custom`, `triggers-events`).
2. `NodeContract`, `NodeRegistry`, `NodeRegistryServiceProvider`, and a minimal node set to prove the engine: `Input`/`Output` (implicit via `runs.input`/`.output`), `Transform`, `Filter`, `Router`(condition), `CallApi` (HTTP), `AskAi` (LLM call via `laravel/ai`), `RunCode`.
3. `WorkflowGraph`, `GraphValidator`, `ConfigSchemaValidator`, `Workflow::replaceGraph()`/`publishVersion()` — draft editing + publish-time validation working end-to-end via the Internal API's `WorkflowBuilderController`.
4. `StartWorkflowRunAction`, `WorkflowRunner`, `GraphAdvancer`, `StepFailureHandler`, node execution `Jobs/Workflows/{ExecuteNodeJob,DispatchNextNodesJob,ResumeWorkflowJob}` — manual-trigger runs complete end-to-end, Run Log queryable on both API surfaces.
5. `LoopCoordinator`, `SubWorkflowCoordinator`, `HumanApproval`/`Wait` node types + `workflow_approvals` — full flow-control node set working.
6. `TemplateResolver`/`ExpressionEvaluator` — templating across node configs.
7. `DryRunner`, `ContractGenerator` — deferred until the builder agent (`AGENTS_PLAN.md` §6) actually needs them.
8. Folders/Tags/StickyNotes — organizational polish, any time after step 3.

## Verification

Feature tests under `tests/Feature/Api/Internal/Workflows` and `tests/Unit/Services/Workflows`, mirroring `docs/PLAN.md`'s existing verification bullets for its Phase 2/4:
- Build a linear `Input → Transform → CallApi → Output` graph via the Internal API, publish it, start a run via both Internal and Public surfaces, assert the `runs`/`NodeRun` rows reach `completed` with expected output, and that the `runs` row has `runnable_type = Workflow::class`.
- A graph with a cycle, a dangling edge, and a duplicate key each rejected at publish time with the right message.
- A `Router` node with two conditioned edges: assert the non-matching branch's nodes end up `skipped`, not left `pending`.
- A `Loop(foreach)` node over a 3-item list: assert 3 child `runs` created, correctly joined, `fail_fast` vs `continue` policy both covered.
- A `HumanApproval` node: assert the run pauses (`awaiting_approval`), and both accept/reject paths resume/fail correctly.
- A `Wait` node: assert callback resolution and timeout expiry (with and without `continue_on_timeout`) both behave as documented above.
