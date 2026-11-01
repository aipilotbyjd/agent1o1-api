# Onboarding API Plan

## Context

The old project (`agent-1o1-api`) never built an onboarding API — only adjacent Billing/Subscription infrastructure (`Plan` model, `SubscriptionController`, Cashier checkout). The frontend (`agent1o1`) already has a fully-built 7-step onboarding wizard (`src/pages/onboarding/`) wired against a specific API contract that doesn't exist on either backend yet. This doc plans that contract from scratch, reusing what the old project's billing code offers where it fits.

`docs/AUTH_PLAN.md` covers login/tokens; `docs/WORKSPACE_PLAN.md` covers workspaces/roles/permissions; this doc covers the first-run flow that sits on top of both.

## Blocking finding: response envelope

The FE's `unwrap()` (`agent1o1/src/api/core/envelope.ts`) does `res.data.data` unconditionally and expects every response shaped `{ success, statusCode, message, data }` — the old backend's `App\Http\Responses\ApiResponse` helper produced exactly this. Nothing in this project does that today; every controller returns raw `response()->json([...])`. This isn't onboarding-specific — it affects every endpoint already built (`AuthController`, `WorkspaceController`, `TwoFactorController`, etc.) and has to land first, as its own change, before onboarding responses can honestly match what's documented below.

## Sequencing

```
Phase 0: Response envelope   ──┐
Phase 1: current_workspace     ├──► Phase 4: Onboarding endpoints ──► Phase 5: Tests + Bruno
Phase 2: Onboarding tracking   │
Phase 3: Plan catalog (free)   ┘
Phase 3.5: Avatar upload ──────┘
```

## Phase 0 — Response envelope

- `app/Http/Responses/ApiResponse.php` — port from `agent-1o1-api` verbatim: `success()`, `created()`, `error()`, `validationError()`, `unauthorized()`, `forbidden()`, `notFound()`, `serverError()`.
- Exception handler mapping in `bootstrap/app.php`'s `withExceptions()` so `ValidationException`/`AuthenticationException`/`AuthorizationException`/`ModelNotFoundException`/generic `Throwable` all render through `ApiResponse::error()`.
- Retrofit every existing controller (`AuthController`, `UserController`, `WorkspaceController`, `WorkspaceMemberController`, `WorkspaceInvitationController`, `ApiKeyController`, `TwoFactorController`) to use `ApiResponse::success([...])` instead of `response()->json([...])`. `response()->noContent()` (204) stays as-is.
- Every existing Bruno `.bru` post-response script updates `res.body.X` → `res.body.data.X`.
- Every existing test's `assertJsonStructure([...])` gets wrapped one level deeper under `'data' => [...]`.

## Phase 1 — `current_workspace` + switch-workspace

The FE's `WorkspaceStep` does `createWorkspace()` → `switchWorkspace(workspace.id)` → `refreshCurrentUser()`; nothing backs this today.

- Migration `add_current_workspace_id_to_users_table`: nullable `foreignId('current_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete()`.
- `User::currentWorkspace(): BelongsTo`.
- `WorkspaceService::switchTo(User $user, Workspace $workspace): void` — verifies membership (`$workspace->members()->where('user_id', $user->id)->exists()`, else `AuthorizationException`), then `$user->update(['current_workspace_id' => $workspace->id])`. Also auto-switch the creator on `WorkspaceService::create()`.
- `UserController::switchWorkspace(SwitchWorkspaceRequest $request)` — `{workspace_id: string}` → `POST /user/switch-workspace` → `UserResource`.
- `UserResource` gains `current_workspace_id`, `current_workspace` (`WorkspaceResource::make($this->whenLoaded('currentWorkspace'))`).

## Phase 2 — Onboarding progress tracking

Columns on `users`, not a separate table — one linear flow per user, not a history (same reasoning as `two_factor_*` columns added directly to `users`).

Migration `add_onboarding_columns_to_users_table`:
```
onboarding_current_step   string, nullable, default 'profile_picture'
onboarding_completed_at   timestamp, nullable
onboarding_dismissed_at   timestamp, nullable
job_role                  string, nullable
discovery_source          string, nullable
```

`app/Enums/Onboarding/OnboardingStep.php` — backed string enum, matches the FE's `mapStepKeyToIndex` exactly:
```php
enum OnboardingStep: string {
    case ProfilePicture  = 'profile_picture';
    case CreateWorkspace = 'create_workspace';
    case InviteTeam      = 'invite_team';
    case RoleSelection   = 'role_selection';
    case ChoosePlan      = 'choose_plan';
    case ConnectApps     = 'connect_apps';
    case DiscoverySurvey = 'discovery_survey';
}
```

`app/Enums/Onboarding/JobRole.php` — backed enum from the FE's `ROLES` constant (Sales, Marketing, Operations, Support, Engineering, Product, Security, HR, Legal, Finance), `label()`/`description()` per case.

`app/Enums/Onboarding/DiscoverySource.php` — backed enum from the FE's `SURVEY_OPTIONS` list.

`User::hasCompletedOnboarding(): bool`; `casts()` gains `onboarding_completed_at`/`onboarding_dismissed_at` → datetime.

## Phase 3 — Plan catalog (free-only for v1)

Full Stripe checkout for paid tiers is separate scope. v1 seeds enough that `POST /onboarding/plan` and `meta.plans` work end-to-end for `free`, with `pro`/`business` existing as inert catalog rows the FE can render without a working checkout.

Migration `create_plans_table` — ported from `agent-1o1-api`:
```
id, name, slug (unique), description (nullable), price_monthly (uint, default 0), price_yearly (uint, default 0),
limits (json), features (json), stripe_product_id (nullable), stripe_price_id_monthly (nullable),
stripe_price_id_yearly (nullable), trial_days (usmallint, default 0), is_active (bool, default true),
sort_order (usmallint, default 0), timestamps
```

`app/Models/Billing/Plan.php` — port `hasFeature()`, `getLimit()`, `stripePriceId()` helpers from old backend.

`PlansDataset` — 3 rows (`free`, `pro`, `business`) matching the FE's hardcoded `PLANS` constant pricing/features. `stripe_price_id_*` left `null` for `pro`/`business` until real Stripe products exist.

`app/Enums/Billing/BillingInterval.php` — `Monthly`/`Yearly` (needed even for the free path since the request shape includes `interval`).

**Deferred**: `SubscriptionController`/`SubscriptionService`, Cashier checkout wiring, Stripe webhooks, `workspaces/{workspace}/subscription/*` routes. The FE's `useOnboardingStripeCheckout` hook already handles a failing checkout call gracefully (`onError: notify.error(...)`), so this can 404 for now without breaking the flow.

## Phase 3.5 — Avatar upload

Referenced by onboarding step 0 but really a general user-profile feature.

- Migration `add_avatar_to_users_table` — nullable `avatar` string column (storage path).
- Storage: local `public` disk for now (no cloud storage configured in this project yet — revisit if that changes).
- `UserController::uploadAvatar(UploadAvatarRequest $request)` — validates `avatar` (`image`, `max:2048`, matching the FE's 2MB check client-side), stores via `Storage::disk('public')->putFile('avatars', ...)`, updates `user.avatar`, deletes the old file if present.
- `UserController::deleteAvatar(Request $request)` — clears the column, deletes the file.
- Routes: `POST /user/avatar`, `DELETE /user/avatar` (both `auth:api`).
- `UserResource` gains `avatar` (full URL via `Storage::url()`).

## Phase 4 — Onboarding endpoints

New namespace `app/Http/Controllers/Api/Internal/V1/Onboarding/OnboardingController.php`.

New service `app/Services/Onboarding/OnboardingService.php`:

- `state(User $user): array` — assembles `{dismissed, completed, percent, current_step, steps[], meta}`:
  - `steps[]` — one `{key, label, description, completed}` per `OnboardingStep` case; `completed` is derived from underlying data (workspace exists, `job_role` set, etc.), never stored redundantly.
  - `percent` — completed-steps / total × 100.
  - `meta.workspace_slug_suggestion` — `Str::slug($user->name)` + uniqueness check. Reuses `WorkspaceService`'s slug logic — promote its private `uniqueSlug()` to `public suggestSlug(string $base): string`.
  - `meta.plans` — `PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('sort_order')->get())`.
  - `meta.job_roles` — `JobRole::cases()` mapped to `{value, label, description}`.
  - `meta.discovery_sources` — `DiscoverySource::cases()` mapped to `{value, label}`.
  - `meta.credential_types` — empty array for v1 (the FE's `ConnectAppsStep` doesn't consume this key yet, only its hardcoded `CONNECTOR_APPS` constant — confirmed no reference in the FE source).
- `inviteTeam(User $user, array $emails, Role $role, ?string $note): array` — thin wrapper delegating to the already-built `WorkspaceInvitationService::invite()` per email against `$user->currentWorkspace`, advances step to `role_selection`, returns fresh `state()`.
- `selectRole(User $user, JobRole $role): array` — sets `job_role`, advances step, returns `state()`.
- `selectPlan(User $user, string $planSlug): array` — validates the plan exists and is active; for `free` this only advances the step (no Cashier subscription record needed — that only happens once Phase-3-full billing ships).
- `submitDiscovery(User $user, DiscoverySource $source): array` — sets `discovery_source`, advances step, returns `state()`.
- `complete(User $user): array` — sets `onboarding_completed_at = now()`, returns final state.
- `dismiss(User $user): void` — sets `onboarding_dismissed_at = now()`.

New Resources: `app/Http/Resources/Api/Internal/V1/Onboarding/{OnboardingStateResource,PlanResource}.php`.

New Requests: `app/Http/Requests/Api/Internal/V1/Onboarding/{InviteTeamRequest,SelectRoleRequest,SelectPlanRequest,SubmitDiscoveryRequest}.php`:
- `InviteTeamRequest`: `emails` (`array`, each `email`), `role` (`Rule::in` assignable workspace roles), `personal_note` (`sometimes`, `string`)
- `SelectRoleRequest`: `job_role` (`Rule::enum(JobRole::class)`)
- `SelectPlanRequest`: `plan_slug` (`exists:plans,slug`)
- `SubmitDiscoveryRequest`: `discovery_source` (`Rule::enum(DiscoverySource::class)`)

New routes (`routes/api/internal/onboarding.php`, required from `index.php`, all `auth:api`):
```php
Route::prefix('user')->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'state']);
    Route::post('dismiss-onboarding', [OnboardingController::class, 'dismiss']);
});
Route::prefix('onboarding')->group(function () {
    Route::post('invite-team', [OnboardingController::class, 'inviteTeam']);
    Route::post('role', [OnboardingController::class, 'selectRole']);
    Route::post('plan', [OnboardingController::class, 'selectPlan']);
    Route::post('discovery', [OnboardingController::class, 'submitDiscovery']);
    Route::post('complete', [OnboardingController::class, 'complete']);
});
```

## Phase 5 — Tests + Bruno

- `tests/Feature/Api/Internal/Onboarding/OnboardingStateTest.php` — response shape, `meta` contents, `percent` math at various completion states.
- `tests/Feature/Api/Internal/Onboarding/OnboardingFlowTest.php` — end-to-end walk through all 7 steps in order, asserts `current_step` advances correctly and `completed_at` lands only after `complete`.
- `tests/Feature/Api/Internal/Onboarding/OnboardingDismissTest.php`.
- New Bruno folder `bruno/Onboarding/` (`folder.bru` seq 5), flattened naming matching `Workspaces/`'s convention: `Onboarding - Get State`, `Onboarding - Invite Team`, `Onboarding - Select Role`, `Onboarding - Select Plan`, `Onboarding - Submit Discovery`, `Onboarding - Complete`, `Onboarding - Dismiss`.

## The 7-step flow, as the FE drives it

| # | step key | FE calls | Backend need |
|---|---|---|---|
| 0 | `profile_picture` | `POST /user/avatar` | Phase 3.5 |
| 1 | `create_workspace` | `POST /workspaces` → `POST /user/switch-workspace` | Phase 1 + existing `WorkspaceController::store` |
| 2 | `invite_team` | `POST /onboarding/invite-team` | Phase 4, wraps existing `WorkspaceInvitationService` |
| 3 | `role_selection` | `POST /onboarding/role` | Phase 2 + 4 |
| 4 | `choose_plan` | `POST /onboarding/plan` or Stripe checkout | Phase 3 (free path only) |
| 5 | `connect_apps` | *(hardcoded on FE, no backend call today)* | none for v1 |
| 6 | `discovery_survey` | `POST /onboarding/discovery` + `POST /onboarding/complete` | Phase 2 + 4 |

Plus: `GET /user/onboarding` (state + meta), `POST /user/dismiss-onboarding` — callable at any step.

## Explicitly out of scope for v1

- Real Stripe Checkout for `pro`/`business` (Phase 3 stubs the catalog only)
- `meta.credential_types` / actual OAuth app-connection backend for "Connect Apps" (FE doesn't call a real endpoint for this yet either)
- S3/cloud storage for avatars (local disk only)
- Two pre-existing FE/BE route-path mismatches noticed in passing, unrelated to onboarding: FE expects `/auth/resend-verification-email` (this project has `/auth/resend-verification`), and `/verify-email/{id}/{hash}` with no `/auth` prefix (this project nests it under `/auth`). Worth a follow-up, not part of this plan.
