# Bring Your Own Key (BYOK) Plan

**Status: implemented (all four phases).** Entry points: `App\Models\Ai\AiProviderCredential`, `App\Services\Ai\ByokProviderRegistrar`, `App\Services\Ai\AiProviderKeyChecker`, `App\Http\Controllers\Api\Internal\V1\Ai\AiProviderCredentialController`, `App\Jobs\Ai\CheckAiProviderCredentialJob` (`ai-credentials:check`, hourly), supported providers in `config/byok.php`. Where the build differs from the plan below:

- The workspace's key is put *ahead of* the platform hop for the same provider, not swapped in for it, so a failover-worthy error (rate limit, quota) still lands on the platform's key. A rejected key (401) is not failover-worthy to the SDK; the hourly re-check takes it out of rotation and notifies whoever relies on it.
- A key the provider rejects is refused on save (422), not stored as invalid. A key the provider couldn't be reached about is stored unvalidated and re-checked in the background.
- Permissions are three, not two: `ai-credential.view` (viewer), `ai-credential.use-personal` (member — add keys only you use), `ai-credential.manage` (admin — team keys).
- Billing went with option 3: a call served by a BYOK key costs no token credits (`CreditMeter::tokenCost`), its tokens still recorded; base/tool/compute/orchestration credits still apply.
- `model_routes.connector_credential_id` is dropped.
- `GET /model-catalog?workspace_id=` counts the member's usable BYOK keys towards `is_available`.
- Knowledge-base embeddings use a BYOK key too, but only for the platform's own embeddings provider (`ai.default_for_embeddings`) and its same model, so stored vectors stay comparable (`ByokProviderRegistrar::embeddingsProvider()`). Private chunks embed on the owner's personal key, searches on the viewer's.
- A provider hop nobody has a key for (no platform key, no workspace key) is skipped, so routes can be enabled for providers only BYOK workspaces pay for.
- Workspace key policy (`WorkspaceAiKeyPolicy`, `GET/PUT /workspaces/{ws}/ai-key-policy`, admins only): `platform_usage` = `fallback` (default) / `when_no_key` (no platform hops once a workspace key covers the call) / `never` (workspace keys only; uncovered models throw `OwnAiKeyRequiredException` and show unavailable), and `allow_personal_keys` (off: personal keys kept but ignored, new ones refused).
- `POST /ai-key-policy/preview` (`AiKeyPolicyImpact`) lists what a stricter policy would break — models becoming unavailable, agents on them, models losing the platform backup, personal keys going unused — so the screen can warn before saving. `POST /ai-provider-credentials/{id}/restore` backs the screen's Undo. `config/byok.php` carries each provider's `key_prefix` and `key_guide` for the connect dialog.

## Context

The model catalog abstraction (`model_catalog`/`model_routes`, `Services\Ai\ModelCatalogResolver`) decouples the public model a user/agent picks from the real backend that executes it — see `app/Services/Ai/ModelCatalogResolver.php`'s docblock and `database/migrations/2026_08_29_130000_create_model_catalog_table.php`. Every route today authenticates with this platform's own `.env`-configured provider key (`config('ai.providers.*')`) — there is no way for a workspace to run a route on its own key. This doc plans that gap: a workspace connecting its own OpenAI/Anthropic/Fireworks/etc. API key so its agent and workflow calls to that provider run on its own account and billing instead of the platform's.

`model_routes.connector_credential_id` already exists as a column (nullable FK to `connector_credentials`) — it was scaffolded during the model-catalog work as a placeholder and is **not used anywhere**; `ModelCatalogResolver::resolve()` only ever reads `execution_provider`/`execution_model_id`. This doc supersedes that column — see "Why not `connector_credentials`" below for why it gets replaced rather than wired up as-is.

## Why not `connector_credentials`

`app/Models/Connectors/Connector.php` / `ConnectorCredential.php` exist specifically for **workflow integration nodes** — Slack, Gmail, GitHub, Google Sheets/Docs/Drive/Calendar (see `database/seeders/ConnectorsDataset.php`). `Connector.auth_type` + `fields`/`oauth` config drive an OAuth flow or a manual-credential form that `Nodes\Integrations\Concerns\ResolvesConnectorCredential` resolves before a node acts on a workflow's behalf. Reusing that table for AI provider keys would mean:

- Seeding fake `Connector` rows (`key: 'openai'`, `key: 'anthropic'`) with no integration node behind them, purely to satisfy the FK — a `Connector.fields`/`oauth` shape built for arbitrary third-party APIs, unused for what's really just one API key per provider.
- "OpenAI"/"Anthropic" showing up in the same connector list as Slack/Gmail in any future UI, which is not how a user thinks about their LLM provider relative to a workflow integration.

What's worth reusing is the **pattern**, not the table: `ConnectorCredentialScope` (`app/Enums/Connectors/ConnectorCredentialScope.php`) already is "bring your own key" in spirit — its own docblock: *"`Personal` is private to `created_by` — hidden from every other member regardless of role, including workspace owners/admins."* `ConnectorCredential::markAsDefault()`/`isVisibleTo()`/`scopeVisibleTo()` solve exactly the "which of several stored keys applies" and "who can see this" questions BYOK needs answered. Extract that behavior into a shared trait both models use, rather than forcing one model to pretend to be the other.

## Scope: provider-level, not model-level

A BYOK credential is scoped to an **execution provider** (`openai`, `anthropic`, `fireworks`, ...) — the same string that appears in `model_routes.execution_provider` — never to a specific `model_catalog` slug. Connecting one OpenAI key covers every catalog entry that happens to route through `openai` (today: `gpt-4o` and `gpt-4o-mini`), now and for any future entry added the same way, without a credential per model.

## Data model: `ai_provider_credentials`

```
id
workspace_id         FK workspaces, cascade
created_by           FK users, nullOnDelete
execution_provider   string — matches model_routes.execution_provider / config/ai.php provider keys
scope                enum: team | personal   (reuse ConnectorCredentialScope's semantics via a shared trait)
is_default           boolean
name                 nullable string — user-facing label ("My personal OpenAI key")
data                 encrypted json — {api_key, ...provider-specific extras e.g. org_id}   [#Hidden]
validation_status    enum: unvalidated | valid | invalid
last_validated_at    nullable timestamp
last_used_at         nullable timestamp
expires_at           nullable timestamp
timestamps, softDeletes
```

Shared with `ConnectorCredential` via a new `Concerns\HasScopedVisibility` trait (or equivalent): `markAsDefault()`, `isVisibleTo(User $user)`, `scopeVisibleTo()`. This is the one place duplication-vs-abstraction tips toward extracting — the same concern (personal/team visibility + one default per group) appearing twice by design, not three unrelated call sites justifying a premature abstraction.

## Resolution: a layer on top of `ModelCatalogResolver`, not inside it

`ModelCatalogResolver` stays exactly as it is — global, workspace-agnostic, cacheable (`Services\Ai\ModelCatalogResolver`). It must not become workspace-aware; that would conflate "which models exist" with "whose key pays for them."

New service: **`Services\Ai\ByokProviderRegistrar`**

```php
public function resolveFor(Workspace $workspace, ?User $actingUser, string $executionProvider): ?string
```

- Looks up the applicable `AiProviderCredential` for `(workspace, executionProvider)`: a personal credential belonging to `$actingUser` first, else the workspace's team default (`is_default = true`, `scope = team`).
- If found and `validation_status = valid`: decrypts the key, registers a uniquely-named runtime provider config (`Config::set("ai.providers.byok:{$credential->id}", [...])`, same `driver` as the static provider entry it's overriding), returns that dynamic name.
- If none found, or the found one is `invalid`/`unvalidated`: returns `null` — caller keeps the static provider name.

**Composition, not integration**: `AgentRunner`/`AskAiNode` build a chain from `ModelCatalogResolver::providerChain($slug)` exactly as today, then for each `provider => model` pair in that chain, call `ByokProviderRegistrar::resolveFor()` and swap the key when it returns a name. Concretely, for `gpt-4o`:

```
ModelCatalogResolver::providerChain('gpt-4o')
  → ['openai' => 'gpt-4o', 'openrouter' => 'openai/gpt-4o']

for each hop: ByokProviderRegistrar::resolveFor($workspace, $actingUser, 'openai')   → maybe 'byok:42'
              ByokProviderRegistrar::resolveFor($workspace, $actingUser, 'openrouter') → null (no override)

final chain passed to ->prompt(): ['byok:42' => 'gpt-4o', 'openrouter' => 'openai/gpt-4o']
```

Failover still works unchanged: if the workspace's own key fails, the chain falls through to the next hop — their key on a fallback provider if they've connected one, the platform's key otherwise, per whatever the catalog's routes already specify.

## Security

- `data` encrypted (`encrypted:array` cast) + `#[Hidden]`, identical to `ConnectorCredential` — never round-trips into API responses.
- **Validate before trusting**: on save, make one cheap real call (a 1-token completion, or a models-list call where the provider supports one) to confirm the key works before setting `validation_status = valid`. A stored-but-broken key must never silently fail a live agent/workflow run first.
- Periodic re-validation: a scheduled job mirroring `app/Jobs/Connectors/RefreshConnectorCredentialJob.php`'s pattern, flipping `validation_status` to `invalid` and notifying the owner (reusing the `ConnectorCredentialExpiredNotification` pattern) if a previously-valid key starts failing.

## API surface

Mirrors `app/Http/Controllers/Api/Internal/V1/Connectors/ConnectorCredentialController.php` exactly, new resource:

```
GET    /workspaces/{workspace}/ai-provider-credentials
POST   /workspaces/{workspace}/ai-provider-credentials
PATCH  /workspaces/{workspace}/ai-provider-credentials/{credential}
DELETE /workspaces/{workspace}/ai-provider-credentials/{credential}
POST   /workspaces/{workspace}/ai-provider-credentials/{credential}/set-default
POST   /workspaces/{workspace}/ai-provider-credentials/{credential}/validate
```

New `Permission::AiCredentialView`/`AiCredentialManage` enum cases (`app/Enums/Workspaces/Permission.php`), consistent with the existing View/Manage pairing convention — not reusing `Permission::ConnectorManage`, since this is a genuinely different resource with a different visibility model (personal keys hidden from workspace owners, same as `ConnectorCredential`'s existing personal scope).

## Open decision: does BYOK bypass platform credit charges?

`Services\Billing\CreditGate`/`RecordRunCreditUsage` currently charge platform credits per token regardless of whose key ran the call. Once a workspace's own key is paying the provider directly, charging full platform credits on top defeats the purpose. Options, to decide before Phase 4:

1. BYOK calls still charge full platform credits (simplest, weakest value proposition for the user).
2. BYOK calls charge zero, or a flat reduced fee.
3. BYOK calls charge nothing but still record token usage for visibility/rate-limiting, without billing it.

Default recommendation: (3), pending confirmation.

## Rollout phases

1. **Phase 1**: `ai_provider_credentials` table + model + shared `HasScopedVisibility` trait (also refactored onto `ConnectorCredential`) + CRUD API + encryption + key-validation-on-save. No resolver wiring yet — inert by explicit choice, fully testable in isolation (mirrors how `model_routes` shipped before anything read it, but documented as deliberate this time).
2. **Phase 2**: `ByokProviderRegistrar` + composed into `AgentRunner::resolveProvider()` and `AskAiNode::execute()`'s chain-building step.
3. **Phase 3**: periodic re-validation job + expiry/invalid-key owner notifications.
4. **Phase 4**: billing policy implementation once the credit-charging question above is settled.

## Non-goals (for now)

- No `model_catalog_id`/`ai_provider_credential_id` link — BYOK is provider-scoped, not model-scoped (see "Scope" above).
- No change to `model_routes.connector_credential_id` beyond planning to stop using it — a future migration should drop it once `ai_provider_credentials` lands, rather than trying to repurpose it.
- No workspace-level UI is built yet — a design mockup exists only as a published Artifact (not committed to this repo) showing what the "AI Providers" settings screen could look like once this and a real frontend both exist.
