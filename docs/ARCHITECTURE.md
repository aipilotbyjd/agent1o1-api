# Target Architecture — Standard Laravel, Organized

Status: **Proposal** · Scope: full `app/` + `routes/` layout · Behavior changes: **none** (moves + namespaces only, except explicitly listed fixes)

This plan keeps the **default Laravel skeleton** — no custom `Modules/` tree, no
package-style providers, nothing a Laravel developer wouldn't recognize from a
fresh `laravel new`. The problems it solves are real (flat hot-spot
directories, one 48 KB route file, AI logic in four places), but every fix uses
a mechanism Laravel itself ships with: subdirectories inside the standard
layers, multiple route files, and framework conventions enforced by CI.

It builds on two decisions the codebase has already made:

1. **Triggers and Runs are polymorphic and shared** between Workflows and
   Agents (see `RESTRUCTURE_TRIGGERS_AND_RUNS.md`, implemented).
2. **Nodes are the single capability library.** A node written once
   (`NodeHandler`) executes inside workflow runs directly and inside agents via
   the `WorkflowNodeTool` adapter.

---

## 1. What's wrong today

| Symptom | Evidence |
|---|---|
| Flat hot-spots that only grow | 61 controllers in `Http/Controllers/Api/V1/`, 30+ services flat in `Services/` |
| Inconsistent domain grouping | `Http/Requests/Api/V1/` **is** grouped by domain (Agent/, Billing/, Workflow/…); Controllers, Resources, Services, Jobs mostly aren't |
| One 48 KB route file | `routes/api.php` — every merge conflict lands here |
| AI logic in four places | `app/Ai`, `app/Agents`, `app/Services/Agent*`, `app/Services/WorkflowBuilder` |

Note what is **not** on this list: `app/Engine/` and `app/Agents/` are
well-structured domain namespaces and stay where they are. `app/Models/` flat
is the Laravel convention and stays flat.

---

## 2. Target layout

The standard skeleton, with one rule applied consistently: **inside each
standard layer, group by domain, using one canonical domain list.**

Canonical domains (used as the subdirectory name in every layer):
`Auth` · `User` · `Workspace` · `Billing` · `Node` · `Credential` ·
`Workflow` · `Trigger` · `Run` · `Agent` · `Assistant` · `Notification` ·
`Platform` (admin settings, activity log, metrics, log streaming)

```
app/
├── Console/Commands/<Domain>/        # Billing/ already exists — finish the pattern
├── Engine/                           # UNCHANGED — workflow runtime
│   ├── Execution/  Graph/  Nodes/  Polling/  Trigger/  Webhook/
├── Agents/                           # UNCHANGED shape — agent runtime
│   ├── Contracts/  Engine/  Internal/  Tools/  User/
├── Contracts/                        # cross-cutting interfaces (NodeHandler, …)
├── Enums/                            # flat — Laravel convention
├── Events/<Domain>/
├── Exceptions/<Domain>/              # already done — Billing/, Workflow/, … 
├── Http/
│   ├── Controllers/Api/V1/<Domain>/  # ← the 61 flat controllers, grouped
│   ├── Controllers/Webhooks/         # unchanged
│   ├── Middleware/
│   ├── Requests/Api/V1/<Domain>/     # already done
│   ├── Resources/V1/<Domain>/        # ← grouped to match
│   └── Response/                     # unchanged
├── Jobs/<Domain>/                    # Trigger/, Run/, Agent/, Assistant/
├── Listeners/<Domain>/
├── Models/                           # UNCHANGED — flat, per Laravel convention
├── Notifications/
├── Observers/
├── Policies/
├── Providers/
├── Services/<Domain>/                # ← the 30+ flat services, grouped
│   │                                 #   (Billing/, Agent/, WorkflowBuilder/ already exist)
└── Traits/

routes/
├── api.php                           # thin: version shell, public routes, requires below
├── api/
│   ├── auth.php  workspaces.php  billing.php  workflows.php  triggers.php
│   ├── runs.php  nodes.php  credentials.php  agents.php  assistant.php
│   └── notifications.php  platform.php
├── channels.php  console.php  web.php
```

Rules:

- **One domain list, every layer.** `Http/Controllers/Api/V1/Workflow/`,
  `Services/Workflow/`, `Jobs/Workflow/`, `routes/api/workflows.php` all refer
  to the same thing. No layer invents its own taxonomy.
- **Small layers stay flat.** Observers (3 files), Policies, Notifications,
  Traits don't get subdirectories until they hurt (~10+ files).
- **No speculative nesting.** No `V2/` until a V2 exists, no empty domain
  folders.

### Route file split — standard Laravel mechanism

`routes/api.php` keeps the version shell and public endpoints and requires the
per-domain files inside the existing groups, preserving all route names
(`v1.workflows.index` etc. must not change — they're referenced by name):

```php
Route::prefix('v1')->as('v1.')->group(function () {
    // public routes stay here …
    Route::middleware('auth:api')->group(function () {
        require __DIR__.'/api/workflows.php';
        require __DIR__.'/api/agents.php';
        // …
    });
});
```

`php artisan route:list` and `route:cache` behave identically — this is a file
split, not a routing change.

### AI consolidation — four places become two

| Today | Target |
|---|---|
| `app/Agents/**` | stays — the agent runtime (engine, tools, contracts) |
| `app/Ai/` (4 files) | fold into `app/Agents/` |
| `app/Services/Agent/`, `app/Services/AgentSkill/` | `app/Services/Agent/` (merge) |
| `app/Services/WorkflowBuilder/` | `app/Services/Assistant/` (rename to match domain list) |
| `Agents/Internal/*` (builder copilot agents) | stays in `app/Agents/Internal/` — it is runtime code |

### Dependency direction (conventions, not walls)

Without modules there are no hard boundaries — but two rules are worth
enforcing with arch tests because breaking them costs real money later:

1. **`Engine/Nodes` stays a leaf.** Node handlers must not import Services,
   Models (beyond what `NodeInput` carries), or Agents. This keeps every node
   usable by both runtimes. Credentials arrive as data on `NodeInput`.
2. **Agent tools that act on the world go through the node adapter.** Any tool
   performing an external action resolves a node via `WorkflowNodeTool` /
   `NodeCatalog` — no parallel HTTP/code implementations. Tools that
   manipulate agent/draft state (`Tools/Draft/*`, skills) stay native.

Pest 4 is already installed; both rules are one `arch()` test each:

```php
arch('node handlers are leaves')
    ->expect('App\Engine\Nodes')
    ->not->toUse(['App\Services', 'App\Agents', 'App\Jobs', 'App\Http']);
```

---

## 3. Migration plan — small PRs, moves never mixed with behavior changes

Every move phase is: `git mv` → namespace sweep → `composer dump-autoload` →
full test suite → PR. Behavior changes (the P0 queue fix, eager-load fixes,
the tool-adapter convergence) ship as **separate PRs** with their own tests —
never inside a move PR.

| Phase | Content | Kind |
|---|---|---|
| 0 | CI gate (Pint + tests + arch tests) · `Model::shouldBeStrict()` · **P0 queue fix** (§5) | behavior/tooling PRs, one each |
| 1 | Group `Http/Controllers/Api/V1/` and `Http/Resources/V1/` by domain | move-only |
| 2 | Group `Services/`, `Jobs/`, `Events/`, `Listeners/` by domain · AI consolidation (§2) | move-only |
| 3 | Split `routes/api.php` into `routes/api/*.php` (route names frozen) | move-only |
| 4 | Converge `WebBrowseTool`/`CodeExecutionTool` onto their node equivalents via the adapter — **with characterization tests first**: capture current input/output behavior of both tools, then swap the implementation and hold the tests green | behavior PR |
| 5 | Performance P1/P2 items (§5), each as its own PR | behavior PRs |

Namespace sweep mechanics (example, phase 1):

```bash
git mv app/Http/Controllers/Api/V1/WorkflowController.php \
       app/Http/Controllers/Api/V1/Workflow/WorkflowController.php
grep -rl 'App\\Http\\Controllers\\Api\\V1\\WorkflowController' app routes tests | \
  xargs sed -i 's/Api\\V1\\WorkflowController/Api\\V1\\Workflow\\WorkflowController/g'
composer dump-autoload && php artisan test
```

Danger spots the sweep must cover beyond `use` statements: string class
references in config files and `bootstrap/app.php`; queued job/listener class
names already serialized in Redis (drain Horizon before deploying a rename of
any queued class); policies/observers registered by class name; morph maps if
any polymorphic column stores FQCNs (check `target_type`/`runnable_type`
values before phase 2 — if they hold class names, add
`Relation::enforceMorphMap()` first, as its own PR).

---

## 4. Laravel-standards workstream

Already right: Form Requests, API Resources, Policies, per-domain config files,
named rate limiters, Horizon, Passport, Reverb, Pulse, Pint, Pest 4. Gaps:

1. **CI gate.** No CI pipeline exists. Add one workflow running
   `vendor/bin/pint --test`, `php artisan test`, and the arch tests. Nothing
   else in this document survives without it.
2. **Model strictness.** In `AppServiceProvider::boot()`:
   `Model::shouldBeStrict(! app()->isProduction());` — lazy-loading
   violations, silently-discarded attributes, and missing-attribute access
   become exceptions in dev/CI, no-ops in production.
3. **Static analysis.** Larastan level 5–6 with a baseline — it catches the
   string references a namespace sweep misses.
4. **Scoped bindings on nested resources.** `workflows/{workflow}/triggers/{trigger}`
   must `->scopeBindings()` so a child belonging to a different parent 404s.
   Audit all nested groups during phase 3.
5. **`env()` only inside `config/`.** Repo-wide audit, not just `app/`:
   `grep -rn "env(" --include='*.php' . --exclude-dir={vendor,node_modules,config}`
   — covers `routes/`, `bootstrap/`, `database/`. Document any intentional
   exceptions (e.g. `bootstrap/app.php` is allowed).
6. **Pest arch presets.** `arch()->preset()->laravel()` and
   `arch()->preset()->security()` alongside the two custom rules in §2.

---

## 5. Performance workstream

### P0 — bug, fix immediately

**The `agents` queue has no consumer.** `RunAgentJob` and `DiagnoseFailedNode`
dispatch to `onQueue('agents')` (`app/Jobs/RunAgentJob.php:34`,
`app/Jobs/DiagnoseFailedNode.php:25`), but Horizon's supervisors only consume
`default`, `engine`, `triggers`, `builder-ai`, `agent-ai`
(`config/horizon.php`). In any environment running Horizon as configured,
queued agent runs and AI-autofix diagnoses sit in Redis forever. Fix: point
both jobs at `agent-ai` (or add a supervisor), plus a test asserting every
`onQueue()` value in `app/` appears in the Horizon config so this class of bug
can't recur.

### P1 — cheap, high leverage

1. **Lazy-loading regression detection** via §4.2 strictness. This catches
   Eloquent lazy-load N+1s on paths the test suite exercises — it is not a
   complete N+1 detector (raw SQL and untested endpoints escape it). Pair it
   with spot query-count assertions on the hottest index endpoints (runs list,
   workflow list, dashboard). Add missing eager loads / `withCount()` as they
   surface.
2. **Trim list payloads.** Run/log/version JSON blobs (`output_data`, node
   configs) must not load on index endpoints — select explicit columns in
   list queries; heavy fields belong to `show` routes only.
3. **phpredis over predis.** Queues, cache, Horizon, and Reverb scaling all
   sit on Redis; the C extension is meaningfully faster and is a
   Dockerfile-only change (`pecl install redis`, `REDIS_CLIENT=phpredis`).
4. **OPcache preloading** in `Dockerfile.web`; `artisan optimize` at boot
   already covers config/route/event caching.
5. **Job uniqueness.** `PollSingleTriggerJob` and scheduled sweepers implement
   `ShouldBeUnique` so an overlapping tick can't double-poll a trigger
   (complements `onOneServer()` from `docs/PLAN.md`).

### P2 — measured, not speculative

6. **Index audit on hot paths**, verified against real query plans: `runs`
   (workspace + status + created_at), trigger polling (`next_poll_at` +
   status), `trigger_events` dedup lookups, `execution_logs` (run + sequence).
7. **Cursor pagination for append-only streams** — run logs, trigger events,
   credit transactions. Offset pagination degrades with depth; these UIs are
   forward-only anyway.
8. **Catalog caching.** Node catalog, credential types, trigger catalog are
   read-heavy and change on deploy/seed only — cache whole responses with
   invalidation on seed (generalizing the 300 s `Cache::remember` already in
   `InspectNodeSchemaTool`).
9. **Retention.** `ExecutionLog` / `TriggerEvent` / `ArchivedExecutionLog` get
   `Prunable` + scheduled `model:prune` with per-plan retention windows.
   Table growth is the slow death of every automation platform; this is also
   a billing differentiator.
10. **Pulse in production.** Already installed — wire slow-query, slow-job,
    and cache recorders; put the dashboard behind the admin gate. Items 6–9
    should be driven by what Pulse shows, not guesses.

### Deliberately deferred

- **Octane / FrankenPHP** — this workload's bottleneck is queue workers, not
  HTTP; the fpm deploy in `docs/PLAN.md` stands until Pulse says otherwise.
- **Read replicas / partitioning** — not before retention has capped growth.

---

## 6. What this buys

- **Zero learning curve.** Every directory is where `laravel new` would put
  it; new hires and every Laravel tutorial stay applicable.
- **Feature work touches one domain slice** — `Controllers/Api/V1/Workflow/`,
  `Services/Workflow/`, `routes/api/workflows.php` — without the ceremony of
  module providers and per-module route registration.
- **Merge conflicts localized.** The route split alone removes the single
  worst conflict magnet in the repo.
- **The two load-bearing boundaries** (nodes as a leaf library, tools through
  the adapter) are enforced by tests, which is the part of "architecture"
  that actually needs enforcement here.
