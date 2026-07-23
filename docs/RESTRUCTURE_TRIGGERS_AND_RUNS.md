# Restructure: Unified Triggers & Runs

Status: **Implemented (clean cut)** · Scope: `triggers` + `runs` seams only · Engines untouched

> Implementation note: this was landed as a **clean polymorphic cut**, not an
> expand/contract with legacy mirror columns. There is no old data to preserve,
> so `triggers`/`trigger_events` use `target_type`/`target_id` as the only
> target columns, and `runs` uses `runnable_type`/`runnable_id` as the only
> runnable columns. The historical `workflow_id`/`agent_id` names survive purely
> as **virtual accessor/mutator aliases** on the models for source-compatibility
> — they are not database columns.
>
> Final model shape: there is now a **single `Run` model** over the `runs`
> table (the `Execution` and `AgentRun` classes were removed). Workflow-only
> relations (`nodes`, `checkpoint`, `logs`, …) and agent-only relations
> (`steps`) both hang off `Run` and are simply empty for the other kind;
> `status` is the `ExecutionStatus` enum for every run. `$workflow->executions()`
> and `$agent->runs()` return `Run` instances scoped by the polymorphic target.

## 0. Goal

Today Workflows and Agents are two parallel automation stacks. They duplicate the
**trigger** layer and the **run/execution** layer. This plan unifies both seams around
two polymorphic concepts — an **Automatable target** and a **Run** — while keeping the
two execution engines (`WorkflowRunner` deterministic DAG, `AgentRunner` LLM tool-loop)
completely separate.

Non-goals: merging the execution loops, merging billing meters, changing the queue
topology, or touching the app-trigger catalog behaviour.

---

## 1. Current state — two of everything

| Concern            | Workflow side                              | Agent side                          |
|--------------------|--------------------------------------------|-------------------------------------|
| Trigger model      | `app/Models/Trigger.php` (rich)            | `app/Models/AgentTrigger.php` (thin)|
| Trigger event      | `app/Models/TriggerEvent.php` (durable)    | — none —                            |
| Webhook controller | `Webhooks/TriggerWebhookController.php`     | `Webhooks/AgentWebhookController.php`|
| Schedule job       | `Jobs/CheckScheduledTriggersJob.php`        | `Jobs/CheckScheduledAgentTriggersJob.php` |
| Poll job           | `Jobs/PollTriggersJob.php` + `PollSingleTriggerJob.php` | — none —                |
| Guarded processor  | `Jobs/ProcessTriggerEventJob.php`           | — none —                            |
| Run record         | `app/Models/Execution.php`                  | `app/Models/AgentRun.php`           |
| Run children       | `ExecutionNode`, `ExecutionCheckpoint`, `ExecutionLog`, `ArchivedExecutionLog`, `ExecutionReplayPack` | — none —      |
| Run service        | `Services/ExecutionService.php`             | `Services/AgentRunRecorder.php`     |
| Run executor job   | `Jobs/ExecuteWorkflowJob.php` / `ResumeWorkflowJob.php` | `Jobs/RunAgentJob.php`   |
| Run controller     | `ExecutionController.php`                    | `AgentRunController.php`            |
| Run resource       | `ExecutionResource.php`                      | `AgentRunResource.php`             |
| Status enum        | `Enums/ExecutionStatus.php`                  | ad-hoc strings on `AgentRun`        |

The **app-trigger catalog** (shared, keep as-is):
`Models/TriggerType.php`, `Models/TriggerCategory.php`, `Models/TriggerTypeField.php`,
`Engine/Trigger/NormalizerRegistry.php` + `Normalizers/*`,
`Engine/Webhook/WebhookRegistrarRegistry.php` + `*Registrar.php`,
`Engine/Polling/PollingRegistry.php` + `Executors/*`,
`Services/TriggerRegistrationService.php`, `Services/TriggerValidationService.php`.
None of these reference Workflow — they operate on `Trigger`. They become shared for free.

---

## 2. Target architecture

```
Automatable (morph: workflow | agent)
        ▲
        │ target
   ┌────┴─────┐
   │ Trigger  │  (webhook | polling | scheduled | manual + app-specific via TriggerType)
   └────┬─────┘
        │ ingest (dedup)
   ┌────▼──────────┐
   │ TriggerEvent  │  durable, statusful
   └────┬──────────┘
        │ ProcessTriggerEventJob: filter → concurrency → rate-limit
   ┌────▼──────────┐
   │ RunDispatcher │  resolves target → creates Run + queues executor
   └────┬──────────┘
        ├─► ExecuteWorkflowJob → WorkflowRunner  ─┐
        └─► RunAgentJob        → AgentRunner      ─┤
                                                   ▼
                                            ┌──────────────┐
                                            │     Run      │ (morph: workflow | agent)
                                            └──────────────┘
```

---

## 3. New shared foundations (additive, zero risk)

| Action | File |
|--------|------|
| NEW | `app/Contracts/Automatable.php` — implemented by `Workflow` + `Agent`; exposes `workspace_id`, `is_active`, `morphClass` |
| NEW | `app/Contracts/Runnable.php` — generalizes existing `AgentRunnable`; `run(RunContext): void` |
| NEW | `app/Contracts/RunRecord.php` — shared run read/write surface |
| NEW | `app/Enums/RunStatus.php` — Pending, Running, Waiting, Completed, Failed, Cancelled (supersedes `ExecutionStatus`; keep `ExecutionStatus` as a deprecated alias during transition) |
| NEW | `app/Enums/TargetType.php` — `Workflow`, `Agent` (backs the morph map) |
| NEW | `app/Engine/RunnerRegistry.php` — resolves the `Runnable` executor for a target type |
| MODIFY | `app/Providers/AppServiceProvider.php` — `Relation::enforceMorphMap(['workflow' => Workflow::class, 'agent' => Agent::class])` |

---

## 4. PHASE T — Triggers

### T1. Expand (add columns, no removals)

NEW migrations under `database/migrations/`:

- `..._make_triggers_polymorphic.php`
  - add `target_type` (string, nullable), `target_id` (uuid, nullable)
  - add `initial_message` (text, nullable), `last_fired_at` (timestamp, nullable)
  - backfill `target_type='workflow', target_id=workflow_id`
  - make `workflow_id` nullable
  - index `['target_type','target_id']`
- `..._make_trigger_events_polymorphic.php`
  - add `target_type`, `target_id`; backfill from `workflow_id`; make `workflow_id` nullable
- `..._migrate_agent_triggers_into_triggers.php`
  - copy each `agent_triggers` row → `triggers` with `target_type='agent'`,
    `target_id=agent_id`, `type` mapped (`schedule`→`scheduled`), `schedule_expression`/
    `schedule_timezone` from `config`, `initial_message`, `settings=config`, generate
    `webhook_uuid` for webhook rows

### T2. Wire (code)

| Action | File | Change |
|--------|------|--------|
| MODIFY | `app/Models/Trigger.php` | add `target(): MorphTo`, `runnable()`, `initial_message`/`last_fired_at` fillable+cast; keep `workflow()` as compat accessor |
| MODIFY | `app/Models/TriggerEvent.php` | add `target(): MorphTo`; fillable `target_type/target_id` |
| MODIFY | `app/Models/Workflow.php` | `triggers(): MorphMany` via `morphMany(Trigger::class, 'target')` |
| MODIFY | `app/Models/Agent.php` | replace `triggers()` hasMany(AgentTrigger) with `morphMany(Trigger::class, 'target')` |
| NEW    | `app/Engine/Trigger/RunDispatcher.php` | resolves target → `ExecutionService::triggerFromEvent` (workflow) or `RunAgentJob::dispatch` (agent); increments `total_executions` |
| MODIFY | `app/Engine/Trigger/TriggerEventDispatcher.php` | write `target_type/target_id` onto the event instead of `workflow_id` |
| MODIFY | `app/Engine/Trigger/TriggerConcurrencyGuard.php` | count running `Execution` (workflow) OR `AgentRun`/`Run` (agent) by target |
| MODIFY | `app/Jobs/ProcessTriggerEventJob.php` | eager-load `trigger.target`; final step calls `RunDispatcher::dispatch()` |
| MODIFY | `app/Services/TriggerService.php` | `create(Automatable $target, array $data)` instead of `create(Workflow $workflow, ...)`; set `target_type/target_id` |
| MODIFY | `app/Jobs/RunAgentJob.php` | trailing `last_fired_at` update targets `Trigger` (not `AgentTrigger`) |
| NO-OP  | `CheckScheduledTriggersJob`, `PollTriggersJob`, `PollSingleTriggerJob` | already generic over `Trigger`; agents picked up automatically |
| NO-OP  | `TriggerRegistrationService`, `WebhookRegistry`, `NormalizerRegistry`, `PollingRegistry`, all `Normalizers/*`, `*Registrar.php`, `Executors/*` | target-agnostic; shared for free |

API surface:

| Action | File | Change |
|--------|------|--------|
| MODIFY | `app/Http/Controllers/Api/V1/AgentTriggerController.php` | create/read/update/delete unified `Trigger` rows (target=agent); `fire()` goes through the pipeline |
| MODIFY | `app/Http/Resources/V1/AgentTriggerResource.php` | thin wrapper over shared `TriggerResource` (or delete + reuse `TriggerResource`) |
| MODIFY | `routes/api.php` | keep `agents/{agent}/triggers` routes; they now hit unified storage |

### T3. Contract (remove the parallel stack)

| Action | File |
|--------|------|
| DELETE | `app/Models/AgentTrigger.php` |
| DELETE | `app/Http/Controllers/Webhooks/AgentWebhookController.php` |
| DELETE | `app/Jobs/CheckScheduledAgentTriggersJob.php` |
| MODIFY | `routes/api.php` — remove `agent-webhooks/{triggerUuid}` route |
| MODIFY | `routes/console.php` — remove the `CheckScheduledAgentTriggersJob` schedule entry |
| NEW    | `..._drop_agent_triggers_table.php` |
| NEW    | `..._drop_workflow_id_from_triggers_and_trigger_events.php` (only after all reads use `target_*`) |

---

## 5. PHASE R — Runs

Target: a single polymorphic `runs` table replacing `executions` + `agent_runs`.
Execution-specific child tables stay, repointed to `run_id`.

### R1. Expand

NEW migrations:

- `..._create_runs_table.php` — superset:
  `id, runnable_type, runnable_id, workspace_id, status, mode, source, trigger_id,
  triggered_by, input, output, error, started_at, finished_at, duration_ms,
  attempt, max_attempts, parent_run_id, wait_token, credits_consumed,
  provider, model, prompt_tokens, completion_tokens, total_tokens, estimated_cost,
  plan, reflections, metadata, timestamps`
- `..._migrate_executions_into_runs.php` — copy with `runnable_type='workflow'`,
  `runnable_id=workflow_id`, `input=trigger_data`, `output=result_data`
- `..._migrate_agent_runs_into_runs.php` — copy with `runnable_type='agent'`,
  `runnable_id=agent_id`, preserving token/cost/plan/reflections
- `..._add_run_id_to_execution_children.php` — add nullable `run_id` to
  `execution_nodes`, `execution_checkpoints`, `execution_logs`,
  `archived_execution_logs`, `execution_replay_packs`; backfill `run_id=execution_id`

### R2. Wire

| Action | File | Change |
|--------|------|--------|
| NEW    | `app/Models/Run.php` | implements `RunRecord`; `runnable(): MorphTo`; `nodes()`, `checkpoint()`, `logs()`; `isPending()`, `isTerminal()`, `refreshSuccessRate` hook |
| MODIFY | `app/Models/ExecutionNode.php` | `run(): BelongsTo(Run::class)` |
| MODIFY | `app/Models/ExecutionCheckpoint.php` | `run()` |
| MODIFY | `app/Models/ExecutionLog.php` | `run()` |
| MODIFY | `app/Models/ArchivedExecutionLog.php` | `run()` |
| MODIFY | `app/Models/ExecutionReplayPack.php` | `run()` |
| MODIFY | `app/Models/Workflow.php` | `runs(): MorphMany(Run)`; keep `executions()` as compat scope |
| MODIFY | `app/Models/Agent.php` | `runs(): MorphMany(Run)` |
| NEW    | `app/Services/RunService.php` | supersedes `ExecutionService` (`trigger`, `triggerFromEvent`, `retry`, `cancel`) + `AgentRunRecorder` (`start`, `complete`, `fail`) |
| MODIFY | `app/Engine/WorkflowRunner.php` | operate on `Run` (status/timing writes) instead of `Execution` |
| MODIFY | `app/Engine/Execution/ExecutionWriter.php` | write `run_id` on node/log rows |
| MODIFY | `app/Jobs/ExecuteWorkflowJob.php` | `Run::find`, `isPending`, pass to `WorkflowRunner` |
| MODIFY | `app/Jobs/ResumeWorkflowJob.php` | `Run::find` |
| MODIFY | `app/Jobs/RunAgentJob.php` | use `RunService` over `Run` instead of `AgentRunRecorder`/`AgentRun` |
| MODIFY | `app/Engine/Trigger/RunDispatcher.php` | create `Run` for both targets via `RunService` |
| NEW    | `app/Http/Controllers/Api/V1/RunController.php` | `index/show/nodes/retry/cancel/destroy` across both target types (workspace-scoped) |
| NEW    | `app/Http/Resources/V1/RunResource.php` | unified; embeds nodes/tokens conditionally by `runnable_type` |
| MODIFY | `routes/api.php` | add `runs` routes; repoint `executions.*` and `agents/{agent}/runs.*` to `RunController` (keep old paths as aliases for back-compat) |

### R3. Contract

| Action | File |
|--------|------|
| MODIFY→DELETE | `app/Models/Execution.php`, `app/Models/AgentRun.php` (remove once no references; or keep as `class Execution extends Run` shims for one release) |
| DELETE | `app/Services/AgentRunRecorder.php` (folded into `RunService`) |
| MODIFY→DEPRECATE | `app/Services/ExecutionService.php` → thin delegate to `RunService`, then delete |
| MODIFY→DEPRECATE | `ExecutionController.php`, `AgentRunController.php` → delegate to `RunController`, then delete |
| MODIFY | `ExecutionResource.php`, `AgentRunResource.php` → alias `RunResource`, then delete |
| NEW    | `..._drop_execution_id_from_children.php` (after `run_id` fully adopted) |
| NEW    | `..._drop_executions_and_agent_runs_tables.php` |
| MODIFY | `app/Enums/ExecutionStatus.php` → alias to `RunStatus`, then delete |

---

## 6. App-trigger catalog (Gumloop-style) — how the breadth is handled

The catalog is the part that scales to "many app triggers". It is **unchanged** by this
work and is what gives agents instant breadth:

- `TriggerType` rows define each integration: `slug`, `execution_mode`
  (webhook/polling/manual), `webhook_events`, `requires_credential`, config `fields`.
- Delivery behaviour is per-provider and pluggable:
  - Webhook verify + register: `WebhookRegistrarRegistry` → `GitHubRegistrar`,
    `SlackRegistrar`, `StripeRegistrar`, `AirtableRegistrar`, `DiscordRegistrar`.
  - Payload shape: `NormalizerRegistry` → `GitHubNormalizer`, `SlackNormalizer`,
    `StripeNormalizer`, `AirtableNormalizer`, `DiscordNormalizer`, `GenericNormalizer`.
  - Polling: `PollingRegistry` → `GmailExecutor`, `GoogleSheetsExecutor`,
    `GenericApiPollingExecutor`.
- All of these take a `Trigger`, not a Workflow. After Phase T they serve agents too.

**Adding a new app trigger (before and after this change):** insert a `TriggerType`
row (+ `TriggerTypeField`s), and — only if the provider needs custom behaviour — add a
`Normalizer` / `Registrar` / `PollingExecutor` class and register it. It then works for
both workflows and agents with no further wiring.

---

## 7. Rollout order & safety

1. **Foundations** (§3) — additive, ship anytime.
2. **T1 → T2 → T3** — expand/migrate/contract for triggers. Steps T1–T2 are
   zero-downtime and reversible (old columns/tables intact). Run T3 only after traffic
   is confirmed flowing through `target_*`.
3. **R1 → R2 → R3** — same discipline for runs. R1–R2 dual-write; R3 removes legacy.

Do Phase T completely, bake it, then Phase R. Never run T3 and R1 in the same deploy.

---

## 8. Tests to update (existing suite)

- Triggers: `tests/Feature/Workflow/WebhookTriggerTest.php`,
  `AppSpecificTriggerTest.php`, `WaitWebhookTest.php`, plus new agent-trigger-through-
  pipeline cases.
- Runs: `tests/Feature/Workflow/WorkflowExecutionTest.php`, `SuccessRateTest.php`,
  `tests/Feature/Agent/AgentRunnerTest.php`, `tests/Unit/NodeRunnerRetryTest.php`,
  `tests/Unit/EngineWorkflowContextTest.php`.
- Add: `RunDispatcherTest`, `TriggerConcurrencyGuardTest` (agent target),
  polymorphic `Run` model tests.

---

## 9. Effort / risk

| Phase | Files touched | Risk | Payoff |
|-------|---------------|------|--------|
| Foundations | ~7 new | 🟢 none | enables everything |
| T1–T2 | ~12 | 🟡 medium | agents get full trigger stack + all app integrations |
| T3 | ~7 | 🟡 medium | delete parallel trigger stack |
| R1–R2 | ~18 | 🟠 med-high | unified runs, one ops surface |
| R3 | ~10 | 🔴 high | remove legacy run tables (FK-heavy) |

Recommended first cut: **Foundations + Phase T**. Reassess before Phase R.
