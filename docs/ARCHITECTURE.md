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

## 7. What this buys

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
