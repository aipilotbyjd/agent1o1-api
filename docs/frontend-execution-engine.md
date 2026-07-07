# Frontend Integration — Workflow Execution Engine

How the React builder drives the **real** backend execution engine: saving a
graph, triggering runs, streaming live progress, and reading run history.

Everything here reflects the current API. Items introduced by the execution
engine work are tagged **[engine]**; everything else already existed.

- API base: `/api/v1`, workspace-scoped under `/api/v1/workspaces/{workspace}/…`
- Auth: Passport bearer token (`Authorization: Bearer <token>`)
- Realtime: Laravel Reverb (Pusher protocol), private channels

---

## 1. The graph the frontend stores

The builder persists the DAG onto the workflow's **current version** as two JSON
columns, `nodes_data` and `edges_data`. The engine compiles and executes exactly
this shape.

### Node

```jsonc
{
  "id": "node_2",              // stable id — referenced by expressions (see §3)
  "type": "http_request",      // maps to a server-side executor (NodeCatalog)
  "name": "Fetch weather",     // display label; NOT used for references
  "config": {                  // field values, may contain {{ }} tokens
    "method": "GET",
    "url": "https://api.example.com/weather?city={{ node_1.output.city }}",
    "retry": { "max_attempts": 3, "backoff": 2 }   // optional, see §4
  }
}
```

### Edge

```jsonc
{
  "source": "node_1",
  "target": "node_2",
  "sourceHandle": "true"       // optional: which branch/port the edge leaves from
}
```

`sourceHandle` is how branch nodes activate only some downstream edges. A
`condition` node emits `true` / `false` handles; only edges whose
`sourceHandle` matches the taken branch fire, others are marked `skipped`.

> **Use ids, not names, in references.** Names break on rename/duplicate; ids are
> stable. The engine topologically sorts (Kahn) and **rejects cycles**, so the
> graph must be a DAG.

---

## 2. Trigger a run

### Manual "Run now"

```
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/execute
Body: { "trigger_data": { "email": "jay@example.com" } }
```

`202 Accepted`:

```jsonc
{
  "message": "Execution queued. Subscribe to the execution channel for real-time updates.",
  "data": {
    "execution": { "id": "…", "status": "pending", … },   // ExecutionResource, §6
    "channel": "private-execution.<execution_id>"          // subscribe here, §5
  }
}
```

`trigger_data` becomes the trigger node's output and is reachable as
`{{ node_1.output.<field> }}` (or `{{ trigger_data.<field> }}`) downstream.

`402` is returned up front if the workspace is out of credits.

### Webhook trigger

```
POST /api/v1/webhooks/{webhookUuid}     (GET also accepted)
```

Public, unauthenticated by session — authenticated by the trigger's configured
signature/secret. The payload is normalized, **deduplicated** (by provider
delivery id / dedup key), and a run is created and dispatched. The `webhookUuid`
comes from the trigger record the frontend created via the triggers API.

### Scheduled / polling triggers

Time-based and polling triggers start runs on their own via a scheduled command;
no frontend call is needed beyond configuring the trigger
(`…/workflows/{workflow}/triggers/{trigger}/schedule` and `/polling-interval`).

---

## 3. Expression / token syntax [engine]

Any string field in `config` may embed `{{ … }}` tokens, resolved server-side
against completed upstream node outputs.

| Form | Example | Notes |
|------|---------|-------|
| **Bare id** *(recommended)* | `{{ node_2.output.city }}` | id-based, stable |
| Namespaced | `{{ nodes.node_2.output.city }}` | equivalent to bare id |
| Nested path | `{{ node_2.output.data.temp }}` | dot access (`data_get`) |
| Array index | `{{ node_2.output.data.0.id }}` | numeric index |
| Variables | `{{ variables.api_base }}` | workspace variables |
| Trigger data | `{{ node_1.output.email }}` | trigger node output |
| Functions | `{{ uppercase(node_1.output.name) }}` | strings/math/dates/arrays/json/logic |

Rules the frontend can rely on:

- **Whole-field single token preserves type.** If a field's *entire* value is one
  token, the raw typed value is returned — `{{ node_2.output.rows }}` yields the
  actual array/object/number, not a stringified copy. Mixed strings interpolate
  (`"Hi {{ node_1.output.name }}"` → `"Hi Jay"`).
- **Missing tokens never crash.** At run time an unresolved token renders as an
  empty string, so a typo or absent upstream field won't abort a node.

Reference expressions by node **id**. When you duplicate/rename a node in the
builder, rewrite tokens to the new id (the label is irrelevant to resolution).

---

## 4. Per-node retry config [engine]

Add an optional `retry` block to any node's `config` to make transient failures
(e.g. a flaky HTTP endpoint) retry with exponential backoff before failing the
run:

```jsonc
"config": {
  "url": "…",
  "retry": {
    "max_attempts": 3,   // total tries incl. the first (default 1 = no retry)
    "backoff": 2,         // base seconds before first retry
    "multiplier": 2,      // exponential factor (delay = backoff * multiplier^(n-1))
    "max_backoff": 60     // cap per delay, seconds
  }
}
```

Defaults come from `config/engine.php` (env-overridable) and default to **1
attempt / fail-fast**, so retries are opt-in per node. When a node exhausts its
attempts the run is marked `failed` and **downstream nodes are `skipped`**. The
attempt count is persisted per step (see `attempt` in §6).

---

## 5. Realtime progress (Reverb)

Subscribe to the private channel returned by the trigger response:

```
private-execution.<execution_id>
```

Authorization: the user must be a member of the execution's workspace (enforced
in `routes/channels.php`).

Events on that channel:

| Event (`broadcastAs`) | Fires when | Key payload fields |
|---|---|---|
| `execution.started` | run begins | `execution_id`, `workflow_id`, `mode`, `started_at` |
| `node.completed` | each node finishes | `execution_id`, `node_id`, `status`, `output`, `error`, `duration_ms`, `sequence` |
| `execution.waiting` | run suspends (wait/delay) | `execution_id`, `reason`, `resume_at`, `webhook_wait_uuid` |
| `execution.completed` | run succeeds | `execution_id`, `status`, `result_data`, `duration_ms`, `finished_at` |
| `execution.failed` | run fails | `execution_id`, `error`, `finished_at` |

Typical UI flow: on `execution.started` mark the run active; update each node's
badge from `node.completed` (`status` ∈ success/failed/skipped); close out on
`execution.completed` / `execution.failed`.

---

## 6. Read run history

All read endpoints are under `/api/v1/workspaces/{workspace}/executions`.

| Method & path | Purpose |
|---|---|
| `GET /executions` | list runs (filterable by status) |
| `GET /executions/{execution}` | run detail (`ExecutionResource`) |
| `GET /executions/{execution}/nodes` | per-step rows (`ExecutionNodeResource[]`) |
| `GET /executions/{execution}/logs` | structured per-run/step logs |
| `POST /executions/{execution}/retry` | re-run a **failed** execution (`202`) |
| `POST /executions/{execution}/cancel` | cancel a running execution |
| `POST /executions/{execution}/replay-pack` | snapshot a run for replay |

### Execution (run) shape

```jsonc
{
  "id": "…",
  "workflow_id": "…",
  "status": "pending|running|completed|failed|cancelled|waiting",
  "mode": "manual|webhook|schedule|…",
  "started_at": "…", "finished_at": "…", "duration_ms": 1234,
  "trigger_data": { … },
  "result_data": { … },
  "error": { "message": "…" },
  "attempt": 1,
  "parent_execution_id": null,
  "credits_consumed": 1
}
```

### Execution node (run step) shape

```jsonc
{
  "id": "…",
  "node_id": "node_2",
  "node_run_key": "node_2",           // unique per run; loop iterations differ
  "node_type": "http_request",
  "node_name": "Fetch weather",
  "status": "pending|running|success|failed|skipped",
  "started_at": "…", "finished_at": "…", "duration_ms": 42,
  "attempt": 1,                        // [engine] how many times the node ran
  "input_data": { "config": { … } },   // [engine] resolved input for debugging
  "output_data": { … },                // raw node output
  "error": { "message": "…" },
  "sequence": 3,                       // execution order
  "loop_index": null                   // set for loop-body iterations
}
```

> **Statuses.** Run: `pending`, `running`, `completed`, `failed`, `cancelled`,
> `waiting`. Step: `pending`, `running`, `success`, `failed`, `skipped`. Render
> `waiting` as "paused" and `skipped` (not-taken branch / upstream failure) as a
> muted badge.

---

## 7. Credentials

- Credentials are managed via `/api/v1/workspaces/{workspace}/credentials`.
- **Secrets are never returned to the frontend** — stored encrypted server-side
  and injected into executors at run time.
- In node `config`, reference a credential by **id** only; never put secret
  values in `config`. OAuth tokens auto-refresh server-side.

---

## 8. End-to-end example

A 3-node run — **form trigger → HTTP → email** — driven from a webhook:

1. `node_1` (trigger): `trigger_data` = `{ "email": "jay@example.com", … }`.
2. `node_2` (http_request): `url` uses `{{ node_1.output.city }}`; response stored
   as `node_2.output`.
3. `node_3` (email): `to = {{ node_1.output.email }}`,
   body references `{{ node_2.output.data.temp }}`.

Result: one `execution` + three `execution_nodes` (`node_run_key` per step), with
each step's resolved `input_data` and raw `output_data` persisted. If `node_2`'s
endpoint is down and it has a `retry` block, it retries with backoff, then the run
fails and `node_3` is recorded as `skipped`.
