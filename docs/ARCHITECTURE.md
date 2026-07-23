# Target Architecture — Modular Monolith

Status: **Proposal** · Scope: full `app/` layout · Behavior changes: **none** (moves + namespaces only)

This document defines the target folder structure for agent1o1-api and the
incremental path to get there. It builds on two decisions the codebase has
already made:

1. **Triggers and Runs are polymorphic and shared** between Workflows and
   Agents (see `RESTRUCTURE_TRIGGERS_AND_RUNS.md`, implemented).
2. **Nodes are the single capability library.** A node written once
   (`NodeHandler`) executes inside workflow runs directly and inside agents via
   the `WorkflowNodeTool` adapter.

The folder structure below makes those two facts visible in the tree instead of
contradicting them.

---

## 1. What's wrong with the current layout

The app is organized **layer-first** (`Http/`, `Models/`, `Services/`, `Jobs/`)
with two domain islands (`Engine/`, `Agents/`). At ~500 PHP files this breaks
down:

| Symptom | Evidence |
|---|---|
| One feature smeared across 8+ folders | "Workflows" lives in `Http/Controllers/Api/V1`, `Http/Requests/Api/V1/Workflow`, `Http/Resources/V1`, `Models`, `Services`, `Jobs`, `Observers`, `Policies`, `Exceptions/Workflow` |
| Flat hot-spots that only grow | 61 controllers in one directory, 71 models in one directory, 30+ services |
| AI logic in four places | `app/Ai`, `app/Agents`, `app/Services/Agent*`, `app/Services/WorkflowBuilder` |
| One 48 KB route file | `routes/api.php` — every merge conflict lands here |
| No enforced boundaries | Anything can `use` anything; the Engine/Agents isolation is convention only |

The two domain islands (`Engine/`, `Agents/`) are the **best-structured parts
of the codebase** — self-contained, contract-driven, easy to navigate. The
restructure generalizes that pattern to everything else.

---

## 2. Target layout

Three rings: **Foundation** (shared kernel, depends on nothing app-level),
**Modules** (bounded contexts), and unchanged framework glue at the root.

```
app/
├── Foundation/                     # shared kernel — no business logic
│   ├── Http/                       #   ApiResponse envelope, ClampPagination,
│   │                               #   base FormRequest/Resource helpers
│   ├── Contracts/                  #   cross-cutting interfaces (NodeHandler lives here)
│   ├── Enums/                      #   enums used by 3+ modules (ExecutionStatus, …)
│   ├── Traits/
│   └── Authorization/              #   workspace context, permission composition
│
├── Modules/
│   ├── Identity/                   # auth, users, onboarding, tokens
│   ├── Workspaces/                 # workspaces, members, invitations, environments, admin settings
│   ├── Billing/                    # plans, subscriptions, credits, packs, usage, Stripe webhook
│   ├── Nodes/                      # ★ the capability library (currently Engine/Nodes + node APIs)
│   ├── Connections/                # credentials, credential types, OAuth flow, token refresh
│   ├── Workflows/                  # authoring: CRUD, versions, folders, tags, variables,
│   │                               #   sticky notes, shares, templates, import/export,
│   │                               #   approvals, releases, contracts, git sync
│   ├── Triggers/                   # polymorphic trigger layer (webhook, polling, schedule,
│   │                               #   normalizers, catalog) — serves Workflows AND Agents
│   ├── Runs/                       # polymorphic run layer: engine runtime (graph walker,
│   │                               #   checkpoints), run history, logs, archives, replay
│   ├── Agents/                     # agents, versions, memory, knowledge, skills, evals,
│   │                               #   conversations, tools, vector store
│   ├── Assistant/                  # AI copilot: workflow-builder sessions/generation,
│   │                               #   autofix, internal agents (suggestion/refinement/…)
│   ├── Notifications/              # channels, preferences, in-app, dispatch
│   └── Observability/              # dashboard, activity log, connector metrics, log streaming
│
├── Console/                        # unchanged (commands may move into modules over time)
└── Providers/                      # AppServiceProvider, HorizonServiceProvider (unchanged)
```

### Inside every module — one fixed shape

```
app/Modules/Workflows/
├── WorkflowsServiceProvider.php    # registers routes, policies, observers, bindings
├── routes.php                      # this module's slice of /api/v1
├── Contracts/                      # ★ the module's PUBLIC API — what other modules may use
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Observers/
├── Services/
├── Jobs/
├── Events/                         # domain events other modules may listen to
├── Listeners/
├── Enums/                          # module-private enums
└── Exceptions/
```

Rules of the shape:

- Missing concern → missing folder. No empty directories.
- `Contracts/` + `Events/` are the module's public surface. Everything else is
  private by convention and enforced by arch tests (§4).
- No `Api/V1` nesting inside modules. Versioning lives in the route prefix; if a
  V2 ever exists it gets `Http/Controllers/V2/` *then*, not speculatively.

### Where migrations, factories, tests go

- **`database/migrations/` stays central.** Timestamps already give global
  ordering; splitting migrations per module buys nothing and complicates
  deploys (`docs/PLAN.md` migration discipline still applies).
- **`database/factories/` stays central** (`Database\Factories\` autoload is
  already wired).
- **Tests mirror modules**: `tests/Feature/Workflows/…`, `tests/Unit/Nodes/…`.
  Current tests already lean this way (`Feature/Execution/`, `Feature/Auth/`,
  `Unit/WorkflowBuilder/`).

### Routing

`routes/api.php` shrinks to the version shell + public endpoints; each module
provider mounts its own `routes.php`:

```php
// Modules/Workflows/WorkflowsServiceProvider.php
public function boot(): void
{
    Route::middleware(['api', 'auth:api'])
        ->prefix('api/v1')
        ->as('v1.')
        ->group(__DIR__.'/routes.php');
}
```

Providers register in `bootstrap/providers.php` (one line per module).

**No composer change is needed**: `App\` already maps to `app/`, so
`App\Modules\Workflows\Models\Workflow` resolves for free.

---

## 3. Module dependency graph

Arrows mean "may depend on" (imports the target's `Contracts/`, models for
reads, or listens to its events). Anything not drawn is **forbidden**.

```
                        Foundation  (everyone may use)
                             ▲
   Identity ── Workspaces ───┤
                             │
   Billing ◄─(events)─── Runs ────► Nodes ◄──── Connections (data only, via NodeInput)
                             │        ▲
                             │        │ (catalog)
   Triggers ────────────► Workflows ──┘
       │                     ▲
       └──────► Runs         │
                             │
   Agents ────► Nodes (via WorkflowNodeTool)
   Agents ────► Runs  (workflow-as-tool, run recording)
                             │
   Assistant ─► Workflows, Nodes, Agents(engine)
   Notifications ◄─(events)── any module
   Observability ◄─(events)── any module
```

Key rules, in words:

1. **Nodes depends on nothing** except Foundation. Credentials arrive as data
   on `NodeInput` — Nodes never imports Connections. This is what keeps the
   capability library reusable by both runtimes.
2. **Triggers and Runs are shared infrastructure**, not Workflow property. The
   polymorphic `target_type`/`runnable_type` cut already made this true in the
   database; the tree now says it too.
3. **Billing is enforced via contract, metered via events.** Modules that spend
   credits depend on `Billing\Contracts\CreditGuard`; Billing listens to
   `Runs\Events\RunCompleted` for metering. Billing never imports Runs
   internals.
4. **Notifications and Observability are pure listeners.** No module imports
   them; they subscribe to events. (ActivityLog writes move behind an event.)
5. **Cross-module Eloquent relations are allowed for reads.** This is Laravel,
   not hexagon purism — `$workflow->workspace` is fine. **Writes go through the
   owning module's service/contract.** The line to hold: never `save()` another
   module's model.
6. **Agents consume capabilities through the adapter.** Any agent tool that
   performs an external action must resolve a node via `WorkflowNodeTool` /
   `NodeCatalog` — no parallel HTTP/code implementations (this retires the
   duplicated logic in `WebBrowseTool` / `CodeExecutionTool`). Tools that
   manipulate agent/draft state (`Draft/*`, skills, `AgentTool`) stay native.

---

## 4. Enforcement — boundaries that hold without discipline

Convention rots; add executable rules. Pest arch tests, one file per module:

```php
// tests/Arch/NodesBoundaryTest.php
arch('nodes is a leaf module')
    ->expect('App\Modules\Nodes')
    ->not->toUse([
        'App\Modules\Agents',
        'App\Modules\Workflows',
        'App\Modules\Runs',
        'App\Modules\Billing',
        'App\Modules\Connections',
    ]);

arch('nothing reaches Billing internals')
    ->expect('App\Modules')
    ->not->toUse('App\Modules\Billing\Services')
    ->ignoring('App\Modules\Billing');
```

Write the arch tests **first**, with `->ignoring()` exceptions for every
current violation. The exception list is the refactor backlog; shrink it to
zero over time. New violations fail CI on day one.

---

## 5. Mapping: where everything moves

| Today | Target |
|---|---|
| `Http/Controllers/Api/V1/*` (61 flat) | each module's `Http/Controllers/` |
| `Http/Requests/Api/V1/<X>/` | owning module's `Http/Requests/` |
| `Http/Resources/V1/` | owning module's `Http/Resources/` |
| `Http/Response/`, `Http/Middleware/` (cross-cutting) | `Foundation/Http/` |
| `Models/*` (71 flat) | owning module's `Models/` |
| `Services/*` (flat) | owning module's `Services/` |
| `Engine/Graph`, `Engine/Execution` | `Modules/Runs/Engine/` |
| `Engine/Nodes/**` | `Modules/Nodes/Handlers/` (Core / Flow / Apps kept) |
| `Engine/Trigger`, `Engine/Webhook`, `Engine/Polling` | `Modules/Triggers/Engine/` |
| `Agents/**` (Engine, Tools, Contracts, User) | `Modules/Agents/…` |
| `Agents/Internal/*` + `Ai/` + `Services/WorkflowBuilder` | `Modules/Assistant/…` |
| `Services/Agent`, `Services/AgentSkill` | `Modules/Agents/Services/` |
| `Authorization/` | `Foundation/Authorization/` |
| `Exceptions/<Domain>/` | owning module's `Exceptions/` |
| `Enums/` | owning module, or `Foundation/Enums/` if used by 3+ modules |
| `Jobs/`, `Events/`, `Listeners/`, `Observers/`, `Policies/` | owning module |
| `Http/Controllers/Webhooks/` | `Triggers` (trigger/agent webhooks), `Billing` (Stripe), `Workflows` (git-sync) |

Module ownership of models, abbreviated:

- **Identity**: User · **Workspaces**: Workspace, WorkspaceMember, Invitation, WorkspaceEnvironment, PlatformSetting
- **Billing**: Plan, Subscription, CreditPack, CreditTransaction, UsagePeriod, UsageDailySnapshot
- **Nodes**: Node, NodeCategory, PinnedNodeData
- **Connections**: Credential, CredentialType, OAuthCredentialState
- **Workflows**: Workflow, WorkflowVersion, WorkflowTemplate, TemplateCollection, WorkflowShare, WorkflowApproval, WorkflowEnvironmentRelease, WorkflowContract*, Folder, Tag, Variable, StickyNote, GitSyncConfig, Artifact
- **Triggers**: Trigger, TriggerEvent, TriggerType, TriggerCategory, TriggerTypeField
- **Runs**: Run, ExecutionNode, ExecutionCheckpoint, ExecutionLog, ArchivedExecutionLog, ExecutionReplayPack, AiAgentStep
- **Agents**: Agent, AgentVersion, AgentMemory, AgentKnowledge, AgentSkill*, AgentTemplate, AgentToolConfig, AgentEval*, AgentMessageRequest, InternalAgentRun, DocumentEmbedding
- **Assistant**: WorkflowBuilderSession, WorkflowBuilderMessage, WorkflowBuilderDraftVersion, AiGenerationLog, AiFixSuggestion
- **Notifications**: NotificationChannel, NotificationPreference, InAppNotification
- **Observability**: ActivityLog, ConnectorMetric, LogStreamingConfig

---

## 6. Migration plan — leaf-first, one module per PR

Every phase is: `git mv` → namespace sweep → `composer dump-autoload` → full
test suite → PR. **Never mix a move with a behavior change.**

| Phase | Move | Why this order |
|---|---|---|
| 0 | Arch-test scaffolding + `Foundation/` (Response, Authorization, shared traits/contracts/enums) | Locks boundaries before anything moves; smallest churn |
| 1 | `Notifications`, `Observability`, `Billing` | Leaf modules, few inbound deps, proves the pattern cheaply |
| 2 | `Nodes` | The keystone. Includes retiring `WebBrowseTool`/`CodeExecutionTool` duplication through the adapter |
| 3 | `Connections`, `Triggers`, `Runs` | Splits `Engine/` along the seams the polymorphic cut already drew |
| 4 | `Identity`, `Workspaces`, `Workflows` | Workflows is the biggest move; do it once the shape is proven |
| 5 | `Agents`, `Assistant` | Consolidates the four AI locations into two modules |
| 6 | Split `routes/api.php` into module `routes.php` files | Last, after controllers have landed — route *names* must not change (`v1.workflows.index` etc. stay stable) |

Namespace sweep mechanics (per module, example for Billing):

```bash
git mv app/Services/Billing app/Modules/Billing/Services
grep -rl 'App\\Services\\Billing' app tests database | \
  xargs sed -i 's/App\\Services\\Billing/App\\Modules\\Billing\\Services/g'
composer dump-autoload && php artisan test
```

Danger spots the sweep must cover beyond `use` statements:

- **String class references**: config files, `bootstrap/app.php` exception
  handlers, queued-job payloads in Redis (drain Horizon before deploying a
  rename of any queued job/listener class), scheduled command references,
  `morph_map` (add one if polymorphic types store FQCNs — check
  `target_type`/`runnable_type` values; if they store FQCNs, introduce
  `Relation::enforceMorphMap()` with short keys **before** phase 3).
- **Policies/observers** registered by class name.
- **Factories**: `Model::factory()` resolution — add `newFactory()` or keep
  the `HasFactory` guessing working via factory `$model` properties.

Total effort estimate: phases 0–2 are a few days; the whole plan is 2–3 weeks
of part-time effort if done strictly one module per PR. There is no
behavior-change risk beyond the string-reference sweep, and each PR is
independently revertable.

---

## 7. Laravel-standards workstream

The codebase already follows the important conventions: Form Requests, API
Resources, Policies, per-domain config files (`config/engine.php`,
`config/workflow.php`, …), named rate limiters, Horizon, Passport, Reverb,
Pulse, Pint and Pest 4 are all in place. The gaps to close, in order:

1. **CI gate.** There is no CI pipeline in the repo. Add one workflow that
   runs `vendor/bin/pint --test`, `php artisan test`, and the arch tests from
   §4. Nothing in this document survives without this.
2. **Model strictness.** Enable in `AppServiceProvider::boot()`:

   ```php
   Model::shouldBeStrict(! app()->isProduction());
   ```

   This turns lazy loading (N+1s), silently-discarded attributes, and
   missing-attribute access into exceptions in dev/CI while staying safe in
   production. Fix what it surfaces before moving files — moves are easier to
   review when tests are strict.
3. **Static analysis.** Add Larastan at level 5–6 with a baseline. The
   namespace sweeps in §6 are exactly the kind of change static analysis
   catches when a string reference is missed.
4. **Scoped bindings on nested resources.** Routes like
   `workflows/{workflow}/triggers/{trigger}` must use `->scopeBindings()` so a
   trigger belonging to another workflow 404s instead of resolving. Audit all
   nested groups in `routes/api.php`.
5. **`env()` only inside `config/`.** One-time audit
   (`grep -rn "env(" app/`); module code reads `config()` exclusively, so
   `artisan config:cache` stays safe.
6. **Pest arch presets.** Alongside the module boundary tests:
   `arch()->preset()->laravel()` and `arch()->preset()->security()` — free
   enforcement of framework conventions and obvious foot-guns.
7. **Backed enums + casts carry into modules.** Existing `app/Enums` split per
   §5; any remaining string-status columns get enum casts as they move.

---

## 8. Performance workstream

### P0 — bug, fix immediately

**The `agents` queue has no consumer.** `RunAgentJob` and `DiagnoseFailedNode`
dispatch to `onQueue('agents')` (`app/Jobs/RunAgentJob.php:34`,
`app/Jobs/DiagnoseFailedNode.php:25`), but Horizon's supervisors only consume
`default`, `engine`, `triggers`, `builder-ai`, `agent-ai`
(`config/horizon.php`). In any environment running Horizon as configured,
queued agent runs and AI-autofix diagnoses sit in Redis forever. Fix: point
both jobs at `agent-ai` (or add an `agents` supervisor) — one-line change,
plus an arch/feature test asserting every `onQueue()` value appears in the
Horizon config so this class of bug can't recur.

### P1 — cheap, high leverage

1. **N+1 elimination via strictness** (§7.2). With `shouldBeStrict()` on, the
   test suite becomes the N+1 detector. Add missing eager loads /
   `withCount()` on index endpoints as they surface, module by module during
   the §6 moves.
2. **Trim list payloads.** Run/log/version JSON blobs (`output_data`, node
   configs, archived payloads) must not load on index endpoints — select
   explicit columns in list queries and keep heavy fields to `show` routes.
3. **phpredis over predis.** The app talks to Redis constantly (queues, cache,
   Reverb scaling, Horizon). The C extension is meaningfully faster than
   predis and is a Dockerfile-only change (`pecl install redis`, flip
   `REDIS_CLIENT=phpredis`). Keep predis as the fallback in composer.json.
4. **OPcache preloading.** `Dockerfile.web` gains
   `opcache.preload=/var/www/html/preload.php` (framework + hot paths);
   `artisan optimize` at boot already covers config/route/event caching.
5. **Job idempotency + uniqueness.** `PollSingleTriggerJob` and the scheduled
   sweepers implement `ShouldBeUnique` so an overlapping schedule tick can't
   double-poll a trigger. (Complements `onOneServer()` from `docs/PLAN.md`.)

### P2 — as modules land

6. **Index audit on hot paths**, verified against real query plans before
   adding: `runs` (workspace + status + created_at — the dashboard/list
   query), trigger polling (`next_poll_at` + status), `trigger_events`
   dedup lookups, `execution_logs` (run + sequence). Composite indexes only
   where a query is measured, not speculatively.
7. **Cursor pagination for append-only streams** — run logs, trigger events,
   credit transactions. Offset pagination on those tables degrades with
   depth; cursor pagination is O(1) and the UI for logs is forward-only
   anyway.
8. **Catalog caching.** Node catalog, credential types, and trigger catalog
   are read-heavy and change only on deploy/seed. Cache whole-catalog
   responses with explicit invalidation on seed (the per-schema 300s
   `Cache::remember` in `InspectNodeSchemaTool` generalizes).
9. **Retention as a feature.** `ExecutionLog`/`TriggerEvent`/`ArchivedExecutionLog`
   get `Prunable` + a scheduled `model:prune` with per-plan retention windows —
   this is both a performance fix (table growth is the slow death of every
   automation platform) and a billing differentiator.
10. **Pulse in production.** Already installed — wire the slow-query, slow-job,
    and cache-interaction recorders and put the dashboard behind the admin
    gate. Decisions in items 6–9 should be driven by what Pulse shows, not
    guesses.

### Deliberately deferred

- **Octane / FrankenPHP**: real gains for this workload profile are in the
  queue workers, not HTTP; the fpm deploy in `docs/PLAN.md` is fine until
  Pulse shows request throughput as the bottleneck.
- **Read replicas / partitioning**: not before retention (item 9) has capped
  table growth.

---

## 9. Combined roadmap

The standards/performance items slot into the §6 phases rather than forming a
separate project:

| Phase | Structure (§6) | Standards + performance |
|---|---|---|
| 0 | Arch tests + `Foundation/` | **P0 queue fix** · CI gate · `shouldBeStrict()` · Pint · Larastan baseline · arch presets |
| 1 | Notifications, Observability, Billing | Pulse recorders wired (Observability move) |
| 2 | Nodes | Catalog caching (P2.8) |
| 3 | Connections, Triggers, Runs | Job uniqueness (P1.5) · cursor pagination + index audit on runs/logs (P2.6–7) · retention (P2.9) |
| 4 | Identity, Workspaces, Workflows | Scoped bindings audit · list-payload trimming (P1.2) |
| 5 | Agents, Assistant | phpredis + preload land in Dockerfiles (P1.3–4) |
| 6 | Route split | Final `env()`/route-name audit |

Each phase still ships as one PR, tests green, no behavior change except the
explicitly-listed fixes (P0 queue, eager loads).

---

## 10. What this buys

- **Feature work touches one directory.** A new Workflows endpoint = one
  module. A new integration = one node handler, instantly available to both
  workflows and agents.
- **Scope control becomes structural.** Deprecating a feature (e.g. contract
  testing) = deleting `Modules/Workflows/…` subtree + its routes file, not a
  50-file archaeology dig.
- **Team scaling.** Modules are ownable; arch tests make boundary reviews
  automatic instead of tribal.
- **Extraction path.** If Runs ever needs to scale independently (it's the hot
  path), the module boundary is the service boundary — but nothing forces that
  day to come early.
