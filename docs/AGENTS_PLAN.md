# Agent Layer Plan

## Context

`docs/STRUCTURE.md` names `Agent`/`AgentSession`/`AgentMessage`/`Skill` and commits to building on the Laravel AI SDK (`laravel/ai`, already installed — see `composer.json` / `docs/PLAN.md`'s stack decisions). The old project (`agent-1o1-api`) already built the whole layer on the same SDK — confirmed by reading its actual `WorkflowBuilderAgent`/`SearchKnowledgeTool`/`AddNodeTool` classes (below), which use exactly `Laravel\Ai\Contracts\{Agent,Conversational,HasTools,Tool}`, `Promptable`, `RemembersConversations`, `Laravel\Ai\Embeddings`, `Laravel\Ai\Tools\Request` — so the SDK usage pattern here is proven, not hypothetical. This doc ports the models, tool-binding design, RAG, memory, skills, evals, and the chat-based workflow builder, renamed per the top-level naming table (`Agent`→`Agent`, `AgentVersion` folded in).

Depends on `docs/WORKFLOWS_PLAN.md` (§1-4 must exist before §6 below — the builder agent edits a workflow graph) and feeds `docs/NODES_CATALOG.md` (agent tool list is the same `NodeContract` catalog workflows use).

## Schema

### `agents` (was `agents`)
```
id, workspace_id (FK cascade), name, slug, description (nullable), instructions (text),
provider (default 'anthropic'), model (nullable), temperature (decimal 3,2 nullable), settings (json nullable),
created_by (FK users, nullOnDelete), timestamps, softDeletes
unique(workspace_id, slug)
```
Version history: old project had a separate `AgentVersion` (`snapshotVersion()` — already read — captures `instructions`/`provider`/`model`/`temperature`/`settings` + attached nodes' pivot config as one immutable JSON blob per version). **Open decision, revisit at build time**: fold this into an `agent_versions` table (same pattern as `workflow_versions`) if agents need publish-pinned versions the way workflow runs pin to a `WorkflowVersion` — likely yes, since an `AgentSession` mid-conversation shouldn't have its behavior change out from under it if someone edits the agent. Default to building it from day one rather than retrofitting: `agent_versions(id, agent_id, version, snapshot json, changed_by, timestamps)`, unique(agent_id, version).

### `agent_sessions` / `agent_messages`
New tables (old project didn't split these out as their own models — conversation state lived in `Laravel\Ai`'s own `RemembersConversations` trait + a `conversation_id` string, e.g. on `WorkflowBuilderSession`). **Reverted per `WORKFLOWS_PLAN.md`'s `runs` table**: rather than agent turns standing apart from run tracking entirely, `agent_sessions` is itself a `runnable` — `AgentRunner` creates one `runs` row per session (or per turn, see Agent turn loop below) with `runnable_type = AgentSession::class`, so a workspace's "Runs" list covers workflow executions and agent invocations together instead of two separate mechanisms:
```
agent_sessions: id, workspace_id (FK cascade), agent_id (FK cascade), user_id (FK nullOnDelete),
  title (nullable), status (default 'active'), last_activity_at (nullable), timestamps

agent_messages: id, agent_session_id (FK cascade), role (enum: user|assistant|tool), content (longText),
  tool_calls (json nullable), tool_call_id (nullable — links a tool-result message to its call),
  usage (json nullable — token/credit accounting, same shape as node_runs.usage), timestamps
  index(agent_session_id, created_at)
```

### `skills` (was `agent_skills`)
```
id, workspace_id (FK cascade), created_by (FK users, nullOnDelete), name, slug, description (nullable),
category (nullable), icon (nullable), color (nullable), tags (json nullable), instructions (longText),
is_shared (bool, default false), version (unsignedInteger, default 1), timestamps, softDeletes
unique(workspace_id, slug), index(workspace_id, is_shared), index(workspace_id, category)
```
`agent_skill` pivot (was `agent_agent_skill`): `agent_id, skill_id, timestamps`.

### `document_embeddings`, `agent_knowledge_collections`, `agent_memories`
Ported as-is from old migrations (already read):
- `document_embeddings`: `workspace_id`, `collection` (default `'default'`), `source`, `chunk_text`, `embedding` (json — **stored as JSON, not a native vector column**, deliberately: old project's migration comment notes this is for sqlite/Postgres driver portability, with a documented follow-up path to a native Postgres `vector` column + `ivfflat` index once `pgvector` is approved as a dependency — carry that same deferral here rather than deciding now), `metadata`.
- `agent_knowledge_collections`: which `document_embeddings.collection`s an agent uses (`agent_id`, `collection`, `mode`). `mode` is `search` (scopes its knowledge tools) or `always` (the collection's hand-added documents are also injected into its instructions). This replaced the old per-agent `agent_knowledge` table: all knowledge lives in the knowledge base.
- `agent_memories`: per-agent, optionally per-user, key/value facts (`key`, `value`, `type` — default `'fact'`, `metadata`) the agent can read/write across sessions — durable memory distinct from `AgentSession`'s per-conversation history.

### `agent_node` pivot (tool binding — was `agent_node`)
This is the most important schema decision in the whole doc, ported verbatim from old project's `2026_07_30_143715_replace_tools_with_agent_nodes.php` migration (already read, comment included below because it's load-bearing):

```
agent_node: id, agent_id (FK cascade), node_id (FK — a Connector node instance, see below, cascade),
  config (json nullable), exposed_fields (json nullable), timestamps
  unique(agent_id, node_id)
```

**Why this shape, verbatim from the old migration's own comment**: "Config values bound at attach time are merged over whatever the model supplies, so a credential or a fixed channel can never be chosen by the LLM. The exposed fields are the config fields the model is allowed to fill — null means every field that isn't already bound." This is the security boundary between "an agent can call Slack" and "an agent can post to `#exec-only` because a user typed a channel name in a prompt" — the workspace member who attaches the tool decides which fields are fixed (credential, channel, base URL) vs. model-fillable (message text), not the model. **Carry this design forward unchanged.**

`node_id` here needs reconciling against `docs/WORKFLOWS_PLAN.md`'s split of the old `nodes` catalog table into PHP `NodeContract` classes + `custom_nodes` rows: since built-ins no longer have a DB row, `agent_node` should key on a `node_type` string (the same string `workflow_nodes.type` uses) rather than a DB foreign key, with `custom:{id}` type strings still resolving through `NodeRegistry` exactly as workflows do. This keeps "what can an agent call" and "what can a workflow node be" backed by the exact same registry.

### `agent_eval_suites` / `agent_eval_cases` / `agent_eval_runs`
Ported as-is (already read in full) — a saved suite of test cases (input + assertions like `{"type":"contains","value":"refund"}` or `{"type":"llm_rubric","value":"Politely declines"}`) run against an `Agent` to produce a pass/fail report, e.g. before publishing an instructions change.

## Models & tool binding

- **`Models/Agents/Agent.php`** — `nodes(): BelongsToMany` through `agent_node`, `->using(AgentToolBinding::class)->withPivot(['config', 'exposed_fields'])`, ported directly from old `Models/Agents/Agent.php::nodes()` (already read). `skills(): BelongsToMany`, `sessions(): HasMany`, `knowledge(): HasMany`, `memories(): HasMany`, `evalSuites(): HasMany`.
- **`Services/Agents/ToolRegistry`** — at agent-run time, builds the actual `Laravel\Ai` tool list handed to the model: for each attached `agent_node` row, wraps the resolved `NodeContract` instance in a `Laravel\Ai\Contracts\Tool` adapter whose `handle()` merges the pivot's `config` over the model-supplied arguments (bound fields win) before calling `NodeContract::execute()`, and whose `schema()` is derived from the node's `configSchema()` filtered down to `exposed_fields`. A `Workflow` attached as a tool (via a `WorkflowTool` wrapper, per `STRUCTURE.md`'s `Ai/Tools/WorkflowTool.php`) calls `StartWorkflowRunAction` the same way a `SubWorkflow` node does.
- **`Skill`** attachment is simpler — no config binding, just injected as additional system-prompt context alongside `instructions()`.

## Agent turn loop (`Services/Agents/AgentRunner`)

Old project splits this two ways, both confirmed by files already read:
1. **Standalone chat** (`AgentSession`/`AgentMessage`): a `SendAgentMessageAction` calls `AgentRunner::run($session, $message)` → creates (or reuses, for a multi-turn session) a `runs` row with `runnable_type = AgentSession::class` → builds a `Laravel\Ai` `Agent`-implementing class from the `Agent`'s `instructions()`/`provider`/`model`/tool list → SDK handles the model call + tool selection loop → each tool call becomes an `agent_messages` row with `role: tool` and gets credit-metered the same way a `NodeRun` does (`Actions/Billing/DeductCreditsAction`, same ledger, different `source_type`) → loop continues until the model returns a final assistant message, at which point the `runs` row is marked `completed`.
2. **Embedded in a Workflow** (`Nodes/AiAutomation/AgentNode` → engine-driven `execute()`): ported from old `Services/Workflows/Handlers/AgentStepHandler.php` (already read in full) — resolves the `Agent` scoped to the run's workspace, template-resolves a prompt from the node's config (`{{ input.message }}` by default) against the run context, calls the agent synchronously (`(new WorkspaceAgent($agent, $run))->ask($prompt)` in the old project — port as `AgentRunner::ask()`), returns `{text, agent_version}` as the node's output with `usage` fed straight into the node's credit accounting. This is exactly `docs/STRUCTURE.md`'s "An Agent can be embedded inside a Workflow via an agent node type" line, now with a concrete implementation to port.

## Knowledge / RAG

`Services/Agents/SearchKnowledgeTool` (auto-attached to every agent that has any `document_embeddings` rows, or explicitly attachable) — ported as-is from old `Ai/Tools/SearchKnowledgeTool.php` (already read in full): embeds the query via `Laravel\Ai\Embeddings::for([$query])->generate()`, pulls every `document_embeddings` row for the workspace (optionally filtered by `collection`), ranks by cosine similarity computed in PHP (see the schema note above on why this isn't a native vector query yet), returns the top N chunks as JSON. Each collection attached to an agent is either searched (`search`) or always in its prompt (`always`, injected by `SkillInjector` within `knowledge_base.max_injected_characters`): "always know this" versus "look this up when relevant," both from the same knowledge base. Only shared, hand-added documents are injected; synced sources (web pages, connected apps) bring outside content, so their collections can only be searched.

**Built, with both halves reachable over the Internal API**:
- `Services/Agents/KnowledgeBase` owns chunking (~1,000 characters, split on paragraph then sentence boundaries, short paragraphs packed together), embedding (batched, since providers cap inputs per call), and the cosine ranking. `SearchKnowledgeTool` is now a thin agent-facing wrapper over it, so what the API's search preview returns and what an agent retrieves are the same code path.
- `workspaces/{workspace}/knowledge-base` — ingest text, list chunks (the vector itself is never serialized; its `dimensions` are, since a dimension mismatch is the usual reason a chunk never ranks), preview a search, list collections, delete a chunk or a whole collection. Ingestion is synchronous and input-capped; a queued ingestion path is the follow-up if corpora outgrow that.
- `workspaces/{workspace}/agents/{agent}/knowledge` — attach, detach and set the `mode` of the agent's collections. The listing reports each collection's hand-added size and how much of the injection budget its `always` collections use, including any documents left out.

## Skills

CRUD-only in scope for this doc: `Skill` is authored (name, description, `instructions` text, optional `category`/`tags`/`icon`/`color`), optionally marked `is_shared` (visible to attach across the whole workspace vs. private to its creator), versioned (`version` incremented on instruction edits — same "don't let the model behind an in-flight session change under it" reasoning as `Agent` versioning above), and attached to one or more `Agent`s via the pivot.

Also CRUD-only: `SkillReference` (`skill_id`, `title`, `content`, `sort_order`) — reference docs for the skill, returned as-is via the API today; no relevance-based/embedding-driven injection into an agent's context yet, that's a follow-on to the knowledge base (build order step 5). `SkillScript` (`skill_id`, `name`, `description`, `language`, `code`, `is_enabled`) — storage only, `language` restricted to an allow-list (`python`/`javascript`/`typescript`/`bash`); no execution path exists yet, running `code` requires a sandboxed executor (subprocess/container with timeout + resource limits) as separate, security-reviewed work — do not wire `code` into any agent tool-call until that exists.

## Evals

`AgentEvalSuite`/`AgentEvalCase`/`AgentEvalRun` + an `EvalJudgeAgent` (old: `Ai/Agents/EvalJudgeAgent.php`, a second `Laravel\Ai` agent whose job is grading — used for `llm_rubric`-type assertions where a simple string match isn't enough) — ported as-is, schema already fully specified above. Scope for the first pass: `contains`/`not_contains`/`equals` assertion types (pure string checks, no LLM call needed) before adding `llm_rubric` (needs `EvalJudgeAgent` wired up) — sequence this as two build-order steps, not one.

## Builder AI agent (chat-based workflow authoring)

Ported as-is from `Ai/Agents/WorkflowBuilderAgent.php` + its full tool set (all already read or named) — this is the single highest-leverage piece of the old project to port faithfully, since its system prompt already encodes the exact interaction contract that makes it reliable (call `list_available_nodes` first; call `inspect_node_schema` before adding an unfamiliar node type; give every step a short unique key; one edge per branch with a matching `condition`; add an `error`-condition edge for failure handling; call `validate_workflow` + `dry_run_workflow` before telling the user it's ready).

- **`Models/Workflows/Builder/WorkflowBuilderSession`** (was `Models/Workflows/WorkflowBuilderSession`, moved under a `Builder/` subfolder per the naming table): `workspace_id`, `user_id`, `workflow_id` (nullable — a session can start with no workflow yet), `conversation_id` (links to the `Laravel\Ai` `RemembersConversations` trail), `title`, `draft_graph` (json), `draft_lock_version` (unsignedInteger — optimistic concurrency so two collaborators/agent-and-human editing the same draft don't silently clobber each other), `status`.
- **`Ai/Agents/WorkflowBuilderAgent`** implements `Laravel\Ai\Contracts\{Agent,Conversational,HasTools}`, uses `Promptable, RemembersConversations` — ported verbatim, constructor takes the `WorkflowBuilderSession`.
- **`Ai/Tools/WorkflowBuilder/*`** — nine tools, each a thin `Laravel\Ai\Contracts\Tool` wrapping a `WorkflowBuilderSession` method: `ListAvailableNodesTool`, `InspectNodeSchemaTool`, `AddNodeTool` (already read in full — validates `config_json` parses, calls `$session->addStep()`), `UpdateNodeTool`, `RemoveNodeTool`, `ConnectNodesTool`, `DisconnectNodesTool`, `ValidateWorkflowTool` (calls `GraphValidator` from `docs/WORKFLOWS_PLAN.md`), `DryRunWorkflowTool` (calls `DryRunner`). All operate on `$session->draft_graph`, not the live `Workflow` — the session's draft is promoted to a real `Workflow`/`replaceGraph()` call only when the user accepts it.
- **`Models/Workflows/Builder/WorkflowBuilderMessage`** and **`WorkflowBuilderDraftVersion`** (undo/redo history of the draft) ported as-is — out of scope to fully spec here, low risk, straightforward CRUD.

## Build order

1. `Agent` + `agent_versions`, `AgentSession`/`AgentMessage`, `SendAgentMessageAction`, `AgentRunner` standalone-chat path (no tools yet) — a bare agent you can chat with.
2. `agent_node` pivot + `ToolRegistry` — attach `NodeContract` connectors as tools, respecting the bound-config/exposed-fields security boundary above. Requires at least the core node set from `docs/WORKFLOWS_PLAN.md` build order step 2 to exist.
3. `AgentNode` (embedded-in-workflow) + `AgentStepHandler` port — requires `docs/WORKFLOWS_PLAN.md` build order step 4 (engine executing).
4. `Skill` CRUD + attachment.
5. `document_embeddings`/`SearchKnowledgeTool` (RAG), per-agent collections (`search`/`always`) + `AgentMemory`.
6. `WorkflowBuilderSession`/`WorkflowBuilderAgent`/tool set — the chat-based builder. Requires `docs/WORKFLOWS_PLAN.md`'s `DryRunner`/`ContractGenerator`/`GraphValidator` to exist first.
7. `AgentEvalSuite/Case/Run` with string-match assertions only.
8. `EvalJudgeAgent` + `llm_rubric` assertions.

## Verification

Feature tests under `tests/Feature/Agents` and `tests/Feature/Api/{Internal,Public}/Agents`, mirroring `docs/PLAN.md`'s existing Phase 5 verification bullet:
- Send an `Agent` a message requiring a tool call to a registered Workflow; assert the workflow actually ran (`SendAgentMessageAction` → `StartWorkflowRunAction`) and the credit ledger recorded both the agent step and the workflow's node runs.
- Attach a connector node with a bound `credential`/`channel` and an exposed `message` field; assert a crafted tool-call argument trying to override the bound channel is ignored (the merge-bound-over-model-supplied behavior, tested directly — this is the one test that must never regress).
- An `AgentNode` embedded in a workflow: assert `{{ input.message }}` templating resolves into the agent prompt and the node's output/usage land in the run as documented.
- `SearchKnowledgeTool`: seed `document_embeddings`, assert the top-ranked result for a query matches the closest-by-construction vector.
- An eval suite with one `contains` and one `llm_rubric` case: assert `AgentEvalRun.passed`/`failed` counts match, with the rubric case mocking `EvalJudgeAgent`'s response.
- `WorkflowBuilderAgent`: a scripted tool-call sequence (`add_node` → `connect_nodes` → `validate_workflow`) against a session, asserting `draft_graph` ends up as the expected shape and `validate_workflow` correctly reports on a deliberately broken intermediate state.
