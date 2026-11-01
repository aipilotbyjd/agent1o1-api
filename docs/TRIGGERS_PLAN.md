# Trigger System Plan

## Context

The old project (`agent-1o1-api`) has its own from-scratch, fully-specified `TRIGGERS_IMPLEMENTATION_PLAN.md` (1700 lines, already written as a portable spec — its own header says "every file described here is created new, with the naming already settled," not a refactor guide of old-project-specific code). It is the smallest lift of the four docs in this set: **this doc is a renaming/re-pathing layer over that plan, not a redesign.** Read the old plan directly for anything not reproduced here — schema, enums, and the two tables that matter most (design decisions, build order) are reproduced below in full so this doc is self-sufficient for implementation.

## Naming/target reconciliation (old plan → this project)

The old plan already uses portable names (`Trigger`, `TriggerEvent`, `TriggerPreset`, `TriggerService`) that need no renaming. Two things do need reconciling against this project's already-committed design:

1. **`Run` stays `Run`** (unchanged — the old plan's shared, polymorphic `Run` model is exactly what `docs/WORKFLOWS_PLAN.md`'s `runs` table reverted back to), **`Agent` → `Agent`**, **`Credential` → `ConnectorCredential`** — per the naming table in the top-level plan. Every reference to `WorkflowRunner::start()` in the old plan's prerequisites table maps to `StartWorkflowRunAction` (`docs/WORKFLOWS_PLAN.md`); `AgentChatService::sendFromTrigger()` maps to `Services/Agents/AgentRunner` (`docs/AGENTS_PLAN.md`).
2. **`triggers.target` (polymorphic `Workflow`|`Agent`) vs. `STRUCTURE.md`'s "trigger as a node category" design** — `docs/STRUCTURE.md` already states triggers are represented on the canvas as `WorkflowNode`s of type `ManualTriggerNode`/`ScheduleTriggerNode`/`WebhookTriggerNode` (category `triggers-events`), with the `Trigger` row being "a materialized, indexed record derived from a trigger-type WorkflowNode's params... kept in sync so the scheduler/webhook router can query it fast without scanning JSON params on every WorkflowNode." Reconcile as follows: **keep the old plan's polymorphic `target_type`/`target_id` morph on `triggers` unchanged** (it already supports pointing at either a `Workflow` or an `Agent` — agent triggers have no canvas node to derive from), but for a `Workflow` target specifically, the `Trigger` row's lifecycle is owned by `Workflow::replaceGraph()`/`publishVersion()`: adding/editing/removing a trigger-type `WorkflowNode` upserts/deletes the corresponding `Trigger` row as a side effect, rather than a trigger being authored independently of the canvas. `Agent` triggers (e.g. "run this agent every morning") have no node to derive from and are authored directly via the `TriggerController` the old plan already specifies.

## Wiring into Workflows/Agents (implementation status: built)

This system is implemented in full against this plan — migrations, models, `TriggerService`, `WebhookSignatureVerifier`, `FireTriggerEvent`/`PollTrigger` jobs, the webhook + Internal CRUD HTTP layer, both console commands, the preset seeder, and Horizon supervisors — and now actually starts runs: the `RunStarter` seam it was built behind is filled.

- **`App\Contracts\Triggers\RunStarter`** (`canRun()`, `isAlreadyRunning()`, `start()`) is the seam, so neither `TriggerService` nor `FireTriggerEvent` knows which kind of target a trigger points at. **`App\Services\Triggers\TargetRunStarter`** is the implementation, bound in `AppServiceProvider::register()` (it replaced the `NullRunStarter` stand-in this plan was written against): a `Workflow` target goes through `StartWorkflowRunAction` — the same call path both API surfaces use, so a triggered run is pinned to `current_version_id` like any other — and an `Agent` target gets a fresh `AgentSession` plus one message through `SendAgentMessageAction`/`AgentRunner`. Both end in a `runs` row, which is what `TriggerEvent::markFired()` records.
- **Not runnable** means: target missing, soft-deleted, an unpublished workflow, or — the tenancy invariant — a target whose `workspace_id` differs from the trigger's. Intake records those as `skipped` rather than queueing a run. `StoreTriggerRequest` enforces the same workspace scope at creation time; `TargetRunStarter` re-checks it at fire time, since a target can be moved or deleted afterwards.
- **Agent-targeted events fire on their own queue** (`config('triggers.agent_fire_queue')`, default `ai-agent`, `supervisor-ai-agent`) with a 320s timeout, since the job blocks on a model call — the plan's own "agent runs on their own queue" decision, now enforced in `FireTriggerEvent`'s constructor. `queue.retry_after` is raised above that timeout to match.
- **A triggered agent's prompt** comes from `config.message` (templated against `{{ payload.* }}`), falling back to the raw payload as JSON.
- **`triggers.target_type`/`target_id`** use a morph map (`TriggerTargetType::Workflow` → `App\Models\Workflows\Workflow`, `TriggerTargetType::Agent` → `App\Models\Agents\Agent`, registered in `AppServiceProvider::configureMorphMap()`); `TriggerTargetType::modelClass()` returns the same pair for the places that need the class without going through a morph lookup.
- **`trigger_events.run_id`** was migrated as `workflow_run_id` before `runs` existed; it is renamed and FK-constrained (`nullOnDelete`) now that the id it stores is polymorphic — a workflow run *or* an agent turn. **`triggers.credential_id`** is still a bare column pending its own constraint.
- **Routes are nested under `workspaces/{workspace}/triggers`**, not `{agent|workflow}/{id}/triggers` as the old plan's routes section has it — see the folder structure below for why.

## Trigger conditions ("Only run when")

Webhook and Polling triggers take a `filters` list; all conditions must match. Each is `{path, operator, value, source}`: `source` is `payload` (default) or `header`. Evaluation uses `Services\Workflows\ConditionEvaluator`, the same engine as the Filter and Router nodes (operator list and comparison rules: `docs/NODES_CATALOG.md`, "Core node behaviour notes"). Runtime: an unevaluable condition makes the event `ignored`, never `fired`. Publish: `GraphValidator` rejects unknown operators, missing paths, bad sources and invalid regex patterns, so mistakes surface in the editor.

## Folder structure

Every path below already follows `docs/STRUCTURE.md`'s existing conventions (`Models/{Domain}/`, `Enums/{Domain}/`, routes-as-folders, `Api/Internal` vs `Api/Public` split) — nothing new is introduced, this is just where the pieces from the sections above land.

```
app/
  Models/
    Triggers/
      Trigger.php
      TriggerEvent.php
      TriggerPreset.php

  Enums/
    Triggers/
      TriggerType.php
      TriggerEventStatus.php

  Services/
    Triggers/
      TriggerService.php
      WebhookSignatureVerifier.php

  Jobs/
    Triggers/
      FireTriggerEvent.php
      PollTrigger.php

  Console/
    Commands/
      Triggers/
        RunDueTriggersCommand.php     (triggers:run-due)
        RetryStuckEventsCommand.php   (triggers:retry-stuck)

  Http/
    Controllers/
      Webhooks/
        WebhookController.php         # public, token-authenticated — lives outside
                                       # Api/Internal and Api/Public entirely, see below
      Api/
        Internal/V1/
          Triggers/
            TriggerController.php         (CRUD, run, rotate-token, events)
            TriggerPresetController.php   (catalog)
    Requests/
      Api/
        Internal/V1/
          Triggers/
            StoreTriggerRequest.php
            UpdateTriggerRequest.php
    Resources/
      Api/
        Internal/V1/
          Triggers/
            TriggerResource.php
            TriggerEventResource.php
            TriggerPresetResource.php

  Contracts/
    Triggers/
      RunStarter.php           # the fire() seam — see "Standing up without
                                # Workflows/Agents" below

  Policies/
    TriggerPolicy.php          # not needed yet — TriggerController uses the
                                # existing Permission::TriggerView/TriggerManage/
                                # RunTrigger cases via requirePermission(), same
                                # as WorkspaceController; add a Policy only if
                                # per-trigger authorization outgrows that

  Providers/
    AppServiceProvider.php    # RateLimiter::for('trigger-hooks', ...) registered here

config/
  triggers.php

database/
  migrations/
    ..._create_trigger_presets_table.php
    ..._create_triggers_table.php
    ..._create_trigger_events_table.php
  factories/
    Triggers/
      TriggerFactory.php
      TriggerEventFactory.php
      TriggerPresetFactory.php
  seeders/
    TriggerPresetsDataset.php

routes/
  webhooks.php               # Route::post('hooks/{token}', WebhookController::class)
                              # — NOT under routes/api/{internal,public}/, since it
                              # authenticates via the trigger's own token, not a
                              # session or API key (see "HTTP layer" above)
  api/
    internal/
      triggers.php            # workspaces/{workspace}/triggers/* CRUD, required from index.php —
                               # nested under {workspace} (not {agent|workflow}/{id}) since
                               # Workflow/Agent route-model-binding doesn't exist yet; the
                               # request body carries target_type/target_id instead (see below)
  console.php                 # Schedule::command('triggers:run-due')->everyMinute()...
                               # Schedule::command('triggers:retry-stuck')->everyFiveMinutes()...

tests/
  Feature/
    Triggers/
      TriggerTest.php
      UpdateTriggerTest.php
      TriggerCatalogTest.php
      RotateTriggerTokenTest.php
      ManualTriggerRunTest.php
      WebhookIntakeTest.php
      WebhookSignatureTest.php
      WebhookDedupeTest.php
      TriggerFilterTest.php
      ScheduleTriggerTest.php
      PollingTriggerTest.php
      FireTriggerEventTest.php
      RetryStuckEventsTest.php
      TriggerCircuitBreakerTest.php
      TriggerEventLogTest.php
      TriggerRateLimitTest.php
```

**Why `webhooks.php` sits outside `routes/api/{internal,public}/`**: both of those surfaces carry a group-level auth middleware (`auth:api` + `workspace.context` for Internal, `auth:api-key` for Public — see `docs/STRUCTURE.md`'s routing block in `bootstrap/app.php`). The webhook endpoint authenticates itself, per-request, via the `{token}` path segment against the `triggers.token` column — wrapping it in either group would mean fighting the group's middleware rather than using it. Register it as its own entry in `bootstrap/app.php`'s `api:` array, same pattern as the two existing entries:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: [
        __DIR__.'/../routes/api/public/index.php',
        __DIR__.'/../routes/api/internal/index.php',
        __DIR__.'/../routes/webhooks.php',
    ],
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

## Schema (ported verbatim — already read in full)

### `trigger_presets`
```
id, category (string — 'github','schedule','slack'), key (unique — 'github.push'), name, description (nullable),
type (webhook|schedule|polling|manual), signature_scheme (nullable — github|stripe|slack),
dedupe_header (nullable — 'X-GitHub-Delivery'), dedupe_payload_path (nullable — 'event_id'),
config (json nullable — merged under user config), fields (json nullable — UI field defs, also validated),
is_active (default true), sort_order, timestamps
index(category, sort_order)
```
At least one of `dedupe_header`/`dedupe_payload_path` must be set on every webhook preset — a preset with neither cannot dedupe retries.

### `triggers`
```
id, workspace_id (FK cascade), target_type + target_id (morphs), type (webhook|schedule|polling|manual),
preset_id (FK trigger_presets, nullOnDelete), config (json nullable — cron, filters, message template),
token (string 64, nullable, unique — webhook URL secret), signing_secret (text, nullable, encrypted cast),
is_active (default true), credential_id (FK connector_credentials, nullOnDelete),
poll_cursor (json nullable), consecutive_failure_count (unsignedInteger, default 0),
last_run_at (nullable), created_by (FK users, nullOnDelete), timestamps
index(type, is_active)  -- the due-trigger scans
```
`token` is the sole lookup key on the public webhook endpoint and must stay fast under provider load — unique + effectively the primary index for that path.

### `trigger_events`
```
id, trigger_id (FK cascade), source (webhook|schedule|polling|manual), status (default 'queued'),
run_id (FK runs, nullOnDelete), payload (json nullable — decoded body the job fires with),
payload_snippet (text nullable — raw body, capped, for forensics), headers (json nullable — allow-listed only),
error (text nullable), delivery_id (nullable), attempts (unsignedInteger, default 0),
duplicate_count (unsignedInteger, default 0), processed_at (nullable), timestamps

unique(trigger_id, delivery_id)        -- THE dedupe guarantee
index(trigger_id, created_at)          -- event log listing
index(status, created_at)              -- the stuck-event scan
```
The old plan's `run_id` name is kept as-is (no longer renamed to `workflow_run_id`): now that `docs/WORKFLOWS_PLAN.md`'s `runs` table is the same shared, polymorphic `Run` model the old plan used, this column is a plain FK to `runs.id` regardless of whether the trigger's target was a `Workflow` or an `Agent` — `AgentRunner::run()` creates a `runs` row (`runnable_type = AgentSession::class`) the same way `StartWorkflowRunAction` does, so no parallel `agent_session_id` column is needed.

## Enums

`Enums/Triggers/TriggerType` (`Webhook`|`Schedule`|`Polling`|`Manual`, with `usesToken()`/`usesCredential()` helpers) and `Enums/Triggers/TriggerEventStatus` (`Queued`|`Running`|`Fired`|`Ignored`|`Skipped`|`Rejected`|`Failed`, plus the intake-only `Duplicate` outcome that is never written to the status column, with `isTerminal()`/`isQueueable()`/`unresolved()` helpers) — ported verbatim from the old plan's §5, already read in full. No changes needed; these are already generic.

## Core pipeline (old plan §1, verbatim)

```
something happens
      │
      ▼
TriggerService::receive()   fast: a few indexed queries + one INSERT
 · dedupe by delivery id     never calls a model, never walks a graph
 · apply config filters
 · check target can run
 · write trigger_events   ──── row is COMMITTED here
      │ then, and only then
      ▼
FireTriggerEvent (queued)   slow: starts a Run, or blocks on
 · claim the event                 an agent's model call
 · TriggerService::fire()
 · mark fired / finish
      │ if the job never comes back
      ▼
triggers:retry-stuck        every 5 min: re-queue anything left
                             `queued` or `running` past its grace period
```
**The one rule that makes it lossless**: the event row is committed before the job is dispatched — a lost dispatch, a killed worker, or a flushed queue all leave a `queued` row for the retry command to recover, which a job-level retry cannot do because no job exists. This is the spine of the whole system; everything else follows from it.

## Service, jobs, HTTP layer, console commands — port from the old plan directly

These sections are long, already fully specified, and generic — implement by reading the old plan's corresponding section, not by re-deriving:
- **`TriggerService`** (old plan §7): `create()`, `receive()` (the fast intake path — dedupe, filter, target-can-run check), `reject()`, `recordFailure()`, `fire()`, `canRun()`, `isAlreadyRunning()` (checked only on the manual-run path — a person double-clicking wants "busy", a provider redelivering wants its event queued regardless).
- **`WebhookSignatureVerifier`** (old plan §8): per-`signature_scheme` (github/stripe/slack) HMAC verification against `payload_snippet`, timestamp staleness checks for Stripe/Slack.
- **Jobs** (old plan §9): `Jobs/Triggers/FireTriggerEvent` (`claim()` guard makes re-dispatch idempotent, `maxExceptions` not `$tries`, `failed()` handler feeds the circuit breaker), `Jobs/Triggers/PollTrigger`.
- **HTTP layer** (old plan §10): `WebhookController` (public, token-authenticated, no auth middleware — see deployment checklist below), `TriggerController` (CRUD + `run`/`rotate-token`/`events`), `TriggerPresetController` (catalog), `TriggerRequest`, resources.
- **Routes** (old plan §11): a public `hooks/{token}` route rate-limited by token (not IP — one noisy provider must not throttle every trigger sharing its egress IPs), plus `{agent|workflow}/{id}/triggers/*` CRUD under both `routes/api/internal/triggers.php` (per `docs/STRUCTURE.md`'s routes-as-folders convention) and a public webhook entry point outside the `Api/Internal`/`Api/Public` auth surfaces entirely (it authenticates via the trigger's own token, not a session or API key).
- **Console commands** (old plan §12): `triggers:run-due` (schedules + polls, writes rows only, starts no runs — the every-minute tick costs the same whether one trigger or a thousand are due; schedule dedupe is by a minute-derived `delivery_id`, not a lock, so it holds across concurrent servers), `triggers:retry-stuck` (recovers stranded `queued`/`running` events past a grace period). Both `withoutOverlapping()->onOneServer()` in `routes/console.php`, requiring a shared cache store.
- **Configuration** (old plan §13): `config/triggers.php` — `failures_before_disable`, `hook_rate_limit_per_minute`, `poll_every_minutes`, stuck-event grace periods.
- **Preset catalog seeder** (old plan §14): ship a first preset set covering the Priority 1 integrations from `docs/NODES_CATALOG.md` (GitHub, Slack, Stripe) plus `schedule.daily`/`schedule.hourly` presets.

## Design decisions worth keeping (verbatim — these are debugging scars, not style)

| Decision | Why |
|---|---|
| Store the event before dispatching the job | The row outlives the job. Covers lost dispatches, flushed queues, killed workers — cases retries cannot help with because no job exists |
| Unique index on `(trigger_id, delivery_id)` | Dedupe as a write-time guarantee. A read-then-write check loses to concurrent deliveries |
| Rejected deliveries stored **without** a delivery id | Otherwise an attacker blocks a legitimate delivery by guessing its id and sending a badly signed request first |
| `maxExceptions` instead of `$tries` | `WithoutOverlapping` releases burn attempts, so a count limit expires a busy trigger's events without ever trying them |
| Circuit breaker counted in `failed()`, not per attempt | An event that succeeds on retry two is not a failure. Counting per attempt lets one flaky event trip the breaker alone |
| `$event->attempts > 0` guard in `failed()` | An event that expired unclaimed never reached the target — that is queue contention, and counting it lets backlog alone disable a healthy trigger |
| Ignored/duplicate answer 200, not 4xx | Providers fan all events at one URL. Errors on unwanted events cause retries, then hook disabling |
| Agent runs on their own queue | An agent blocks for as long as the model takes; workflow events must not queue behind it |
| `isAlreadyRunning()` only on the manual path | A person double-clicking wants to be told "busy". A provider redelivering wants its event queued |
| Terminal check inside `claim()` | Makes a re-dispatch idempotent. Without it, the retry command can start a second run for an event that already fired |
| Schedule dedupe by minute-derived id | Works across concurrent servers, which a per-process lock does not |

## Build order (ported verbatim from old plan §15)

| # | Step | Deliverable |
|---|---|---|
| 1 | Migrations + enums | 3 tables, `TriggerType`, `TriggerEventStatus`. `migrate:fresh` passes |
| 2 | Models + factories | `Trigger`, `TriggerEvent`, `TriggerPreset` + factories with provider states |
| 3 | Preset seeder | `migrate:fresh --seed` populates the catalog |
| 4 | `TriggerService` | `create`, `receive`, `reject`, `recordFailure`, `fire`, `canRun`, `isAlreadyRunning` |
| 5 | `FireTriggerEvent` | Queued firing, `claim()` guard, `failed()` handler |
| 6 | `WebhookSignatureVerifier` + `WebhookController` | Public endpoint end-to-end |
| 7 | `TriggerRequest` + `TriggerController` + resources + routes | Full CRUD API |
| 8 | `RunDueTriggersCommand` + scheduler | Schedule triggers fire |
| 9 | `PollTrigger` + a polling preset | Polling works |
| 10 | `RetryStuckEventsCommand` | Recovery path |
| 11 | Horizon config + queue tuning | Production-ready |

Steps 1–7 are the minimum viable system (webhook + manual). 8–11 add the remaining types and durability. This slots into the top-level plan's overall build order as step 3 ("Triggers"), after `docs/WORKFLOWS_PLAN.md` steps 1-4 exist (a `Run` needs somewhere to start from).

## Test plan (ported verbatim from old plan §16)

One Pest feature test file per behavior: `TriggerTest`, `UpdateTriggerTest`, `TriggerCatalogTest`, `RotateTriggerTokenTest`, `ManualTriggerRunTest`, `WebhookIntakeTest`, `WebhookSignatureTest`, `WebhookDedupeTest`, `TriggerFilterTest`, `ScheduleTriggerTest`, `PollingTriggerTest`, `FireTriggerEventTest`, `RetryStuckEventsTest`, `TriggerCircuitBreakerTest`, `TriggerEventLogTest`, `TriggerRateLimitTest`. Full per-file assertion list is in the old plan §16 — port the table as-is, renaming `Run`/`Agent` references per the top-level naming table.

**Three tests worth writing carefully, because they encode the invariants** (old plan's own emphasis, keep it):
1. **Dedupe under concurrency** — insert the same `delivery_id` twice, assert one row plus an incremented `duplicate_count`, exercising the `UniqueConstraintViolationException` catch, not just a read-check.
2. **Claim is exclusive** — call `claim()` twice on the same event, assert the second returns `false`.
3. **Event survives a lost dispatch** — create a `queued` event with no job, run `triggers:retry-stuck` past the grace period, assert the job is dispatched.

## Deployment checklist (ported verbatim from old plan §18)

- Queue driver is not `sync`
- Workers running for both `triggers` and `triggers-agent` queues (maps to this project's `Queue::TriggersPoll`/`Queue::TriggersEvent`/`Queue::AiAgent` enum cases — `docs/STRUCTURE.md`'s Horizon table)
- Agent worker timeout ≥ 320s; `queue.retry_after` > longest job timeout
- Scheduler running (`schedule:run` every minute, or `schedule:work`)
- Shared cache store configured for `onOneServer()`
- `APP_KEY` set and stable — `signing_secret` uses the `encrypted` cast, and rotating the key without re-encrypting makes every stored secret unreadable
- Preset seeder wired into the deploy pipeline
- Webhook URL reachable from the public internet and **not** behind auth middleware
- Retention policy for `trigger_events` — this table grows without bound; add a prune command before it becomes a problem
