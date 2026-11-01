# Laravel Folder Structure

Standard Laravel top-level namespaces (`Models`, `Http`, `Jobs`, `Services`, `Providers`, ...). `Ai/` follows Laravel's own AI SDK scaffolding convention (`make:agent` / `make:tool` generate into `App\Ai\Agents\*` and `App\Ai\Tools\*`). No custom `Domain/` wrapper — feature grouping happens as subfolders inside the conventional Laravel folders. Naming uses `Workflow`, not `Flow`.

The Auth / Workspace / Billing pieces below are **not new design** — they already exist, working, in the old project (Passport, Cashier, `WorkspaceContext`, credit-metering via `CreditTransaction`/`UsagePeriod`). They're included here fitted into this structure's conventions so the new `Workflows`/`Nodes`/`Agents` layer has its foundation accounted for in one place — not reinvented.

The API is split into two surfaces, **Public** and **Internal** (see "Public vs. Internal API" below) sharing one `Actions/` business-logic layer, which is also what the MCP server (`Mcp/`) calls into directly.

```
app/
  Models/
    User.php
    Workspaces/
      Workspace.php
      WorkspaceMember.php
      WorkspaceInvitation.php
    Billing/
      Plan.php
      Subscription.php
      CreditPack.php
      CreditTransaction.php
      UsagePeriod.php
      ProcessedWebhookEvent.php
    Runs/
      Run.php
      NodeRun.php
    Workflows/
      Workflow.php
      WorkflowVersion.php
      WorkflowNode.php
      WorkflowEdge.php
      Trigger.php
    Nodes/
      NodeCategory.php
      CustomNode.php
    Agents/
      Agent.php
      AgentSession.php
      AgentMessage.php
      Skill.php
    Connectors/
      Connector.php
      ConnectorCredential.php
    Artifacts/
      Artifact.php
    Auth/
      ApiKey.php

  Authorization/
    WorkspaceContext.php

  Actions/
    Workflows/
      StartWorkflowRunAction.php
      StopWorkflowRunAction.php
      DuplicateWorkflowAction.php
      CreateWorkflowVersionAction.php
    Agents/
      SendAgentMessageAction.php
      CreateAgentSessionAction.php
    Billing/
      DeductCreditsAction.php
    Connectors/
      ConnectOAuthCredentialAction.php

  Ai/
    Agents/
      DynamicAgent.php
    Tools/
      Connectors/
        Slack/
          SlackSendMessageTool.php
        Gmail/
          GmailSendEmailTool.php
      WorkflowTool.php

  Nodes/
    AiAutomation/            (category slug: ai-automation)
      AskAiNode.php
      ExtractDataNode.php
      GenerateImageNode.php
    TriggersEvents/          (category slug: triggers-events)
      ManualTriggerNode.php
      ScheduleTriggerNode.php
      WebhookTriggerNode.php
    FlowLogic/                (category slug: flow-logic)
      FilterNode.php
      RouterNode.php
      LoopNode.php
      WaitNode.php
      JoinPathsNode.php
      ErrorShieldNode.php
    DataTransform/             (category slug: data-transform)
      InputNode.php
      OutputNode.php
      MergeNode.php
      FormatNode.php
    Custom/                     (category slug: custom — see note below)
    Integrations/
      HttpRequest/CallApiNode.php    (category slug: http-request)
      Gmail/GmailSendEmailNode.php
      GoogleSheets/...
      GoogleDrive/...
      GoogleCalendar/...
      Slack/SlackSendMessageNode.php
      Discord/...
      Telegram/...
      Twilio/...
      GitHub/...
      GitLab/...
      Jira/...
      Linear/...
      Trello/...
      Notion/...
      Airtable/...
      HubSpot/...
      Salesforce/...
      Stripe/...
      Mailchimp/...
      SendGrid/...
      OpenAi/...
      AwsS3/...
      Dropbox/...
      MySql/...
      PostgreSql/...
      MongoDb/...
      Redis/...

  Contracts/
    NodeContract.php
    ConnectorContract.php

  Enums/
    Workspaces/
      Role.php
      Permission.php
    Billing/
      SubscriptionStatus.php
      CreditTransactionType.php
      BillingInterval.php
    PortType.php
    NodeCategory.php
    TriggerType.php
    RunStatus.php
    NodeRunStatus.php
    Queue.php

  Jobs/
    Workflows/
      DispatchNextNodesJob.php
      ExecuteNodeJob.php
      ExecuteWorkflowJob.php
      ResumeWorkflowJob.php
      DiagnoseFailedNodeJob.php
    Ai/
      RunAgentJob.php
      ProcessAgentMessageJob.php
      ProcessBuilderMessageJob.php
    Triggers/
      PollTriggersJob.php
      PollSingleTriggerJob.php
      CheckScheduledTriggersJob.php
      CheckScheduledAgentTriggersJob.php
      ProcessTriggerEventJob.php
    System/
      RefreshOAuthTokenJob.php

  Events/
    Runs/
      RunCompleted.php
      RunFailed.php
    Agents/
      AgentSessionMessageSent.php
  Listeners/
    Workflows/
      RecordRunCreditUsage.php
      NotifyOnRunFailure.php

  Exceptions/
    InsufficientCreditsException.php
    WorkflowValidationException.php
    ApiKeyInvalidException.php

  Services/
    Workflows/
      NodeRegistry.php
      NodeExecutionContext.php
      NodeResult.php
      TypeChecker.php
    Agents/
      AgentRunner.php
      ToolRegistry.php
      SkillInjector.php
    Connectors/
      Slack/
        SlackConnector.php
      Gmail/
        GmailConnector.php
    Billing/
      CreditMeter.php

  Http/
    Controllers/
      Api/
        Public/V1/
          Workflows/
            WorkflowController.php
          Runs/
            RunController.php
          Agents/
            AgentController.php
            AgentSessionController.php
          Artifacts/
            ArtifactController.php
          Skills/
            SkillController.php
        Internal/
          Auth/
            LoginController.php
            RegisterController.php
            LogoutController.php
            LogoutAllController.php
            RefreshTokenController.php
            ForgotPasswordController.php
            ResetPasswordController.php
            ChangePasswordController.php
            VerifyEmailController.php
            ResendVerificationController.php
            Session/
              IndexSessionController.php
              RevokeSessionController.php
            Social/
              RedirectToSocialProviderController.php
              HandleSocialCallbackController.php
            TwoFactor/
              EnableTwoFactorController.php
              ConfirmTwoFactorController.php
              DisableTwoFactorController.php
              VerifyTwoFactorController.php
              RegenerateRecoveryCodesController.php
              ShowRecoveryCodesController.php
            User/
              ShowUserController.php
              UpdateUserController.php
              DeleteUserController.php
            ApiKey/
              IndexApiKeyController.php
              StoreApiKeyController.php
              RevokeApiKeyController.php
          Workspaces/
            WorkspaceController.php
            WorkspaceMemberController.php
            AcceptInvitationController.php
          Billing/
            BillingController.php
            PlanController.php
            SubscriptionController.php
            CreditController.php
          Workflows/
            WorkflowController.php
            WorkflowVersionController.php
            WorkflowBuilderController.php
          Runs/
            RunController.php
          Triggers/
            TriggerController.php
          Nodes/
            NodeCategoryController.php
            NodeController.php
          Agents/
            AgentController.php
            AgentSessionController.php
          Connectors/
            ConnectorController.php
          Artifacts/
            ArtifactController.php
          Skills/
            SkillController.php
    Requests/
      Api/
        Public/V1/
          Runs/
            StartRunRequest.php
          Agents/
            SendAgentMessageRequest.php
        Internal/
          Auth/
            LoginRequest.php
            RegisterRequest.php
          Workflows/
            StoreWorkflowRequest.php
            UpdateWorkflowRequest.php
          Agents/
            StoreAgentRequest.php
    Resources/
      Public/V1/
        RunResource.php
        WorkflowResource.php
        AgentResource.php
      Internal/
        WorkspaceResource.php
        SubscriptionResource.php
        WorkflowResource.php
        RunResource.php
        AgentResource.php
    Middleware/
      EnsureWorkspaceScope.php
      EnsureApiKeyIsValid.php

  Mcp/
    Server.php
    Tools/
      RunWorkflowTool.php
      ListWorkflowsTool.php
      SendAgentMessageTool.php

  Policies/
    WorkflowPolicy.php
    AgentPolicy.php
    ConnectorPolicy.php

  Providers/
    AppServiceProvider.php
    NodeRegistryServiceProvider.php
    ToolRegistryServiceProvider.php

bootstrap/
  app.php
  providers.php

config/
  workflows.php
  ai.php
  horizon.php

database/
  migrations/
    ..._create_workspaces_table.php
    ..._create_workspace_members_table.php
    ..._create_workspace_invitations_table.php
    ..._create_plans_table.php
    ..._create_subscriptions_table.php
    ..._create_credit_packs_table.php
    ..._create_credit_transactions_table.php
    ..._create_usage_periods_table.php
    ..._create_processed_webhook_events_table.php
    ..._create_api_keys_table.php
    ..._create_workflows_table.php
    ..._create_workflow_versions_table.php
    ..._create_workflow_nodes_table.php
    ..._create_workflow_edges_table.php
    ..._create_runs_table.php
    ..._create_node_runs_table.php
    ..._create_triggers_table.php
    ..._create_node_categories_table.php
    ..._create_custom_nodes_table.php
    ..._create_agents_table.php
    ..._create_agent_sessions_table.php
    ..._create_agent_messages_table.php
    ..._create_skills_table.php
    ..._create_connectors_table.php
    ..._create_connector_credentials_table.php
    ..._create_artifacts_table.php
  factories/
    WorkspaceFactory.php
    WorkflowFactory.php
    AgentFactory.php
    ApiKeyFactory.php
  seeders/
    DatabaseSeeder.php
    PlansDataset.php
    NodeCategoriesDataset.php

routes/
  api/
    public/
      index.php
      workflows.php
      runs.php
      agents.php
      artifacts.php
      skills.php
    internal/
      index.php
      auth.php
      workspaces.php
      billing.php
      workflows.php
      workflow_builder.php
      runs.php
      triggers.php
      nodes.php
      agents.php
      connectors.php
      artifacts.php
      skills.php
  web.php
  console.php

tests/
  Feature/
    Auth/
      LoginTest.php
      TwoFactorTest.php
    Workspaces/
      WorkspaceMembershipTest.php
    Billing/
      SubscriptionTest.php
    Api/
      Public/
        StartRunTest.php
        ApiKeyAuthTest.php
      Internal/
        CreateWorkflowTest.php
        RunWorkflowTest.php
    Agents/
      AgentToolCallTest.php
    Mcp/
      RunWorkflowToolTest.php
  Unit/
    Nodes/
      AskAiNodeTest.php
    Services/
      TypeCheckerTest.php
    Actions/
      StartWorkflowRunActionTest.php
```

## Public vs. Internal API

Two separate route surfaces, one shared logic layer:

- **`Api/Internal/V1/*`** — the app your own React canvas talks to. Auth via Passport Password Grant (see `docs/AUTH_PLAN.md`), resolved workspace via `workspace.context` middleware (`EnsureWorkspaceScope`). Lives at prefix `/api/v1` — **versioned** (a deliberate choice, made explicitly against the general "Internal deploys in lockstep, versioning buys nothing" argument — the project owner chose consistency with the Public surface's versioning over that argument). Free to be chatty/UI-shaped (`WorkflowBuilderController` exists purely for editor concerns — node placement, edges, autosave — separate from plain resource CRUD).
- **`Api/Public/V1/*`** — external developers and this app's own MCP server. Auth via workspace-scoped `ApiKey` (hashed, rate-limited by plan) through `EnsureApiKeyIsValid`. Lives at prefix `/api/public/v1` — the explicit `public` segment keeps it unambiguous against Internal's `/api/v1` in logs, docs, and rate-limit rules, since both surfaces are versioned now. Intentionally narrower than Internal: exposes running/listing/inspecting (Runs, Workflows, Agents, Artifacts, Skills), not workflow-editing internals.
- **`Actions/`** is the shared business-logic layer both surfaces call into (e.g. `StartWorkflowRunAction`), so the two APIs never duplicate — or drift on — what "start a run" actually does. Controllers stay thin: resolve the actor/workspace, validate via a `Requests/` class, call the Action, wrap the result in a `Resources/` class shaped for that surface.
- **`Policies/`** are shared, not duplicated per surface — authorization is a property of the actor+resource, not the transport. Both surfaces call the same `Gate::authorize()`; only how the actor was resolved (session user vs. API key → workspace) differs upstream.
- **`Mcp/Tools/*`** call `Actions/` directly, not the Public HTTP API — avoids a redundant network hop and auth re-translation, since the MCP server runs in-process.
- **`Models/Auth/ApiKey.php`** is managed from the Internal side (`Http/Controllers/Api/Internal/V1/Auth/ApiKeyController`) — users create/revoke their own public API keys from the app UI.
- **Side effects decoupled via `Events/`+`Listeners/`**: e.g. `RunCompleted` triggers credit deduction and failure notifications without `StartWorkflowRunAction` (or the jobs it kicks off) needing to know about every consumer — the same event fires whether the `runs` row's `runnable_type` is a `Workflow` or an `AgentSession`.
- **Domain exceptions mapped once**: `InsufficientCreditsException` → 402, `WorkflowValidationException` → 422, `ApiKeyInvalidException` → 401, mapped centrally in `bootstrap/app.php`'s `->withExceptions()` — both surfaces get consistent error shapes without each controller catching/formatting individually.

### Routes as folders, not flat files

`routes/api/public/` and `routes/api/internal/` each hold one `index.php` entry file (sets the shared `prefix()`/`middleware()` group once) plus one file per domain, required from the entry file:

```php
// routes/api/public/index.php
Route::prefix('public/v1')
    ->middleware(['auth:api-key', 'throttle:public-api'])
    ->group(function () {
        require __DIR__.'/workflows.php';
        require __DIR__.'/runs.php';
        require __DIR__.'/agents.php';
        require __DIR__.'/artifacts.php';
        require __DIR__.'/skills.php';
    });
```

```php
// routes/api/internal/index.php
Route::prefix('v1')
    ->middleware(['auth:api', 'workspace.context'])
    ->group(function () {
        require __DIR__.'/auth.php';
        require __DIR__.'/workspaces.php';
        require __DIR__.'/billing.php';
        require __DIR__.'/workflows.php';
        require __DIR__.'/workflow_builder.php';
        require __DIR__.'/triggers.php';
        require __DIR__.'/nodes.php';
        require __DIR__.'/agents.php';
        require __DIR__.'/connectors.php';
        require __DIR__.'/artifacts.php';
        require __DIR__.'/skills.php';
    });
```

Each domain file (e.g. `routes/api/public/workflows.php`) holds only its route list, no group boilerplate — it inherits prefix/middleware from `index.php`. Adding a new surface later (e.g. a future `routes/api/mcp/` transport) is one new folder plus one line wiring it into `bootstrap/app.php`; nothing inside `public/` or `internal/` has to change.

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: [
        __DIR__.'/../routes/api/public/index.php',
        __DIR__.'/../routes/api/internal/index.php',
    ],
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

If your installed Laravel version's `withRouting()` only accepts a single `api:` path rather than an array, fall back to a single `routes/api.php` that requires both `index.php` files, and point `api:` at that instead.

## Auth, Workspaces, Billing — already-built, fitted into this structure

- **`User`** stays flat at `Models/User.php` (Laravel convention, not nested) — `HasApiTokens` (Passport) for Internal-side API auth, 2FA fields, `ownedWorkspaces()`/`workspaces()` relations.
- **`Workspace`** is the tenant boundary directly — no separate `Organization` wrapper. It uses Cashier's `Billable` trait itself (`$workspace->subscription()`, `$workspace->charge()`, etc. all work out of the box), and owns everything workspace-scoped (`workflows()`, `runs()`, `credentials()`, `nodes()` for custom ones, `apiKeys()`, ...).
- **`Authorization/WorkspaceContext`** resolves `(Workspace, User) → Role` with request-scoped memoization (via `Illuminate\Support\Facades\Context`, safe under Octane) plus a 5-minute cache — `$context->allows(Permission::X)` is the authorization check used everywhere instead of ad-hoc role string comparisons. `Enums/Workspaces/{Role,Permission}` define the actual role/permission set. On the Public side, `ApiKey` resolves directly to its owning `Workspace` — no per-user role needed since a key already carries the workspace + scope it was issued for.
- **Billing is Cashier + a credit layer on top**, not something to design from scratch: `Subscription`/`Plan` are standard Cashier-adjacent billing; `CreditPack`/`CreditTransaction`/`UsagePeriod` are the metering system — every AI/node execution cost becomes a `CreditTransaction` against the workspace's `currentUsagePeriod()`. `ProcessedWebhookEvent` makes Stripe webhook handling idempotent (dedupe by Stripe event ID before processing). This replaces the `CreditLedgerEntry` from earlier drafts — `CreditTransaction` already is that ledger.
- **Auth routes** live under `Http/Controllers/Api/Internal/V1/Auth/` — a single `AuthController` covering login, register, password reset/change, email verification, session listing/revocation, and social login (Socialite), plus a separate `ApiKeyController` and (once built) `TwoFactorController` — see `docs/AUTH_PLAN.md` for why these are consolidated rather than one-controller-per-action. `Actions/Billing/DeductCreditsAction` (via `Services/Billing/CreditMeter`) and the workflow engine's execution jobs are the two places that actually *write* `CreditTransaction` rows; everything else just reads.

## Queues & Horizon

Every queue name is a typed `Queue` enum case (`Enums/Queue.php`), never a raw string — jobs declare their queue via `->onQueue(Queue::WorkflowExecute->value)`, so a typo can't silently create an unmonitored queue no supervisor is watching. Names are dot-namespaced by domain (`domain.action`) so related queues sort/cluster together in the Horizon dashboard and logs:

| `Queue` case | Value | Fed by (`Jobs/`) | Supervisor |
|---|---|---|---|
| `WorkflowDispatch` | `workflows.dispatch` | `DispatchNextNodesJob` | `supervisor-workflows-dispatch` |
| `WorkflowExecute` | `workflows.execute` | `ExecuteNodeJob`, `ExecuteWorkflowJob`, `ResumeWorkflowJob`, `DiagnoseFailedNodeJob` | `supervisor-workflows-execute` |
| `AiAgent` | `ai.agent` | `RunAgentJob`, `ProcessAgentMessageJob` | `supervisor-ai-agent` |
| `WorkflowBuilder` | `workflow-builder` | `ProcessWorkflowBuilderMessageJob` | `supervisor-workflow-builder` |
| `TriggersPoll` | `triggers.poll` | `PollTriggersJob`, `PollSingleTriggerJob`, `CheckScheduledTriggersJob`, `CheckScheduledAgentTriggersJob` | `supervisor-triggers-poll` |
| `TriggersEvent` | `triggers.event` | `ProcessTriggerEventJob` | `supervisor-triggers-event` |
| `Billing` | `billing.webhook` | Stripe webhook processing | `supervisor-billing-webhook` |
| `Maintenance` | `system.maintenance` | `RefreshOAuthTokenJob` | `supervisor-system` |
| `Notification` | `system.notification` | Email/SMS/in-app sends | `supervisor-system` |

**Why split this many supervisors**: `workflows.execute` and `ai.agent` are isolated from each other so a burst of cheap node executions can't starve slow, rate-limited LLM calls (or vice versa) — `maxProcesses` on `supervisor-ai-agent` acts as a real concurrency ceiling against provider rate limits regardless of other load. `triggers.poll` (bulk/periodic) is split from `triggers.event` (webhook-fired, latency-sensitive) so a big polling batch can't delay a live webhook. `billing.webhook` gets its own supervisor with the highest OS-level priority (`nice: -10`) since it's money-adjacent and must never be delayed by workflow load. `system.maintenance`/`system.notification` share one low-priority supervisor since both are low-volume and can tolerate lag.

`config/horizon.php` defines `waits` thresholds per queue (alerting if jobs sit too long — tuned per queue's actual latency expectations, e.g. 10s for `triggers.event` vs 120s for `triggers.poll`), `trim` retention (failed jobs kept 7 days for debugging, recent/completed trimmed to 1 hour), and three `environments` (production/staging/local) scaling `maxProcesses` per tier. `routes/console.php` schedules `horizon:snapshot` every 5 minutes for the dashboard's metrics graphs.

## How node folders tie to `NodeCategory`

`NodeCategory` is a DB table (seeded via `NodeCategoriesDataset`) — it's the **product-facing taxonomy**: what shows up in the node picker, with `icon`, `color`, `description`, `sort_order`, and a `kind` of `core` (built-in categories) or `app` (one per integration). Every `NodeContract::category()` returns a string that must match a `NodeCategory.slug` row — that's the single source of truth the frontend node picker groups by. The `Nodes/` PHP folders exist purely so the codebase mirrors that same taxonomy 1:1, so "which folder is this node in" and "which category does it render under" are never two different answers:

| `NodeCategory.slug` | `kind` | `Nodes/` folder |
|---|---|---|
| `ai-automation` | core | `Nodes/AiAutomation/` |
| `triggers-events` | core | `Nodes/TriggersEvents/` |
| `flow-logic` | core | `Nodes/FlowLogic/` |
| `data-transform` | core | `Nodes/DataTransform/` |
| `custom` | core | `Nodes/Custom/` (see below — not static classes) |
| `gmail`, `slack`, `github`, `stripe`, ... | app | `Nodes/Integrations/{Service}/` |

**`Triggers & Events` as a node category** means trigger types (`ManualTriggerNode`, `ScheduleTriggerNode`, `WebhookTriggerNode`) are ordinary `NodeContract` classes placed on the canvas as the flow's entry point (no inputs, only outputs) — not a separate polymorphic `Trigger` model driving execution directly. The `Trigger` model still exists, but as a **materialized, indexed record** derived from a trigger-type `WorkflowNode`'s params (e.g. cron expression, webhook secret), kept in sync so the scheduler/webhook router can query it fast without scanning JSON params on every `WorkflowNode`.

**`Your Custom Nodes` (`custom` category)** is different in kind from the rest: these are *user-defined at runtime*, not PHP classes shipped in the codebase. `Nodes/Custom/` holds the generic execution machinery (likely a single `CustomNode implements NodeContract` that interprets a `CustomNode` DB record — user-provided code/config, akin to a saved `RunCodeNode` configuration), while the actual definitions live in the `custom_nodes` table, scoped per workspace. `NodeRegistry` resolves built-in types to classes, but resolves `custom:{id}` type strings to a `CustomNode` DB row instead.

## Namespace-to-purpose map

| Folder | Purpose |
|---|---|
| `Models` | Eloquent models only, grouped into per-feature subfolders (`Models/Workflows/Workflow.php` → `App\Models\Workflows\Workflow`) |
| `Authorization` | `WorkspaceContext` — role/permission resolution for the current caller+workspace |
| `Actions` | Shared business logic, one use case per class, called by `Api/Public`, `Api/Internal`, and `Mcp/` controllers/tools alike |
| `Ai` | Laravel AI SDK classes (`Agent`, `Tool` implementations) — matches `make:agent`/`make:tool` scaffolding |
| `Nodes` | `NodeContract` implementations — the Flow-builder step library |
| `Contracts` | Interfaces shared across the app (`NodeContract`, `ConnectorContract`) |
| `Enums` | Typed enums (roles, permissions, port types, statuses) instead of magic strings |
| `Jobs` | Queued execution units for the workflow engine |
| `Events` / `Listeners` | Decoupled side effects (credit deduction, notifications) off of domain events like `RunCompleted` |
| `Exceptions` | Domain exceptions mapped centrally to HTTP status codes in `bootstrap/app.php` |
| `Services` | Stateless orchestration/business logic (registries, runners, type checking, credit metering) that `Actions/` calls into |
| `Http` | Controllers/Requests/Resources/Middleware — the API surface, split into `Api/Public/V1` and `Api/Internal` |
| `Mcp` | MCP server + tools, calling `Actions/` directly rather than the Public HTTP API |
| `Policies` | Authorization, shared by both API surfaces |
| `Providers` | Binds `NodeRegistry`/`ToolRegistry` at boot |
