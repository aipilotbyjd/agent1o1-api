# Workspace, Roles & Permissions Plan

## Context

`docs/STRUCTURE.md` already names `Workspace` as the tenant boundary (no separate `Organization` wrapper) and `Authorization/WorkspaceContext.php` as the role/permission resolver, but neither was ever built out in detail for this project. The old project (`agent-1o1-api`) already designed and ran this exact system — a bespoke (no Spatie package) workspace + hierarchical role + permission-enum design — so this plan ports it directly, adapted to this project's actual domains (Workflows/Nodes/Agents/Connectors/Artifacts instead of the old project's equivalents).

`docs/AUTH_PLAN.md` covers login/tokens; this doc covers what a user gets access *to* once logged in.

## Schema

`workspaces`:
```
id, name, slug (unique), avatar (nullable), owner_id (FK users, cascadeOnDelete), timestamps, softDeletes
+ Cashier billable columns (stripe_id, pm_type, pm_last_four, trial_ends_at) — Workspace is the Billable model, not User
```

`workspace_members` (its own table with a primary key — not a bare pivot — so it's independently soft-deletable and queryable):
```
id, workspace_id (FK cascade), user_id (FK cascade), role (string, cast to Role enum),
invited_by (FK users, nullOnDelete), joined_at (nullable timestamp), timestamps, softDeletes
unique(workspace_id, user_id)
```

`workspace_invitations`:
```
id, workspace_id (FK cascade), email, role (string, cast to Role enum), token (unique),
invited_by (FK users, cascade), expires_at, accepted_at (nullable), timestamps, softDeletes
```

## Models & relations

- `Models/Workspaces/Workspace.php` — `Billable` (Cashier), `HasFactory`, `SoftDeletes`. `owner(): BelongsTo`, `members(): HasMany` (→ `WorkspaceMember`, the full model — used for CRUD/role changes), `users(): BelongsToMany` (through `workspace_members`, `withPivot('role', 'joined_at')`). Override `stripeEmail()` to return `$this->owner?->email` (Stripe receipts need a real email; a workspace doesn't have one).
- `Models/Workspaces/WorkspaceMember.php` — `role` cast to `Enums/Workspaces/Role`.
- `Models/Workspaces/WorkspaceInvitation.php` — `role` cast to `Enums/Workspaces/Role`.
- `Models/User.php` — `ownedWorkspaces(): HasMany` (via `owner_id`), `workspaces(): BelongsToMany` (through `workspace_members`).

**The owner is a computed role, not a stored one**: `owner_id` on `Workspace` is the source of truth for ownership; `WorkspaceContext::resolveRole()` checks `owner_id === $user->id` before ever touching `workspace_members`. `WorkspaceService::create()` *also* inserts a `workspace_members` row for the owner (role `Owner`) purely so member-listing queries include them — but that row is never what's authoritative. This avoids owner-role drift if a `workspace_members.role` value were ever edited directly.

## Cashier wiring (`AppServiceProvider`)

```php
Cashier::useCustomerModel(Workspace::class);
Cashier::useSubscriptionModel(\App\Models\Billing\Subscription::class);
Cashier::ignoreRoutes(); // register your own webhook controller instead
```

## Business logic — `Services/Workspaces/` (not `Actions/`, same reasoning as auth)

Workspace management is Internal-only (never called from Public API or MCP), so it follows the old project's multi-method-service pattern rather than `STRUCTURE.md`'s `Actions/`-per-use-case split — that split exists specifically for logic shared across surfaces, which doesn't apply here.

- `WorkspaceService.php`:
  - `create(User $owner, array $data)` — DB transaction: create `Workspace` (auto-slug via a `uniqueSlug()` helper if not given) + a `WorkspaceMember` row for the owner (`Role::Owner`, `joined_at => now()`).
  - `update()`, `delete()` (cascades member/invitation soft-deletes, then deletes the workspace), `updateAvatar()` (stores to `public` disk under `workspaces/avatars`).
  - `updateMemberRole()` — blocks changing the Owner's role.
  - `removeMember()` — blocks removing the Owner.
  - `leave()` — blocks the Owner from leaving (must transfer ownership first; throw `AuthorizationException` otherwise).
- `WorkspaceInvitationService.php`:
  - `invite()` — rejects if the email is already a member; creates a `WorkspaceInvitation` with a `Str::random(40)` token, 7-day expiry; queues an invitation email.
  - `accept()` — validates not-already-accepted, not-expired, invited-email-matches-accepting-user; **restores** a soft-deleted `WorkspaceMember` if one exists (handles re-invite after removal) else creates a new one; marks `accepted_at`.
  - Accept URL is a **signed route**, expiring alongside the invitation itself — same pattern as email verification in `docs/AUTH_PLAN.md`.

`Observers/WorkspaceMemberObserver.php` — on `saved`/`deleted`/`restored`, calls `WorkspaceContext::forget($workspaceId, $userId)` to bust the cached/memoized role (see below). This observer is what keeps the permission cache correct when roles change mid-session — don't skip it.

## Roles & Permissions — bespoke, no `spatie/laravel-permission`

### `Enums/Workspaces/Role.php`

```php
enum Role: string {
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';
    case Viewer = 'viewer';
}
```

Hierarchical and additive — each role's `permissions()` layers on top of the role below it (Viewer ⊂ Member ⊂ Editor ⊂ Admin ⊂ Owner). `has(Permission $permission): bool` checks membership in that computed set. `assignable(): array` returns every role except `Owner` — Owner can only ever come from `Workspace.owner_id`, never assigned via an invite or role-update endpoint.

### `Enums/Workspaces/Permission.php`

Dot-namespaced by domain, adapted from the old project's ~70-case enum to this project's actual domains (`STRUCTURE.md`'s `Workflows`/`Nodes`/`Agents`/`Connectors`/`Artifacts`, not the old project's equivalents):

```
workspace.view / workspace.update / workspace.delete
member.view / member.invite / member.update-role / member.remove
invitation.view
workflow.view / workflow.manage / workflow.publish / workflow.version / workflow.trigger / workflow.builder.use
node.view / node.manage                          (custom nodes)
agent.view / agent.manage / agent.chat / agent.skill.manage
connector.view / connector.manage                (holds credentials — Admin-level, not Editor)
artifact.view / artifact.manage
run.view / run.trigger
trigger.view / trigger.manage
api-key.view / api-key.manage
billing.view / billing.manage
```

Grouped by four static methods mirroring the role hierarchy, same shape as the old project:
- `viewerGrants()` — every `*.view` permission.
- `memberGrants()` — interactive-but-non-authoring (`agent.chat`, `workflow.trigger`, `run.trigger`).
- `editorGrants()` — full CRUD authoring (`workflow.manage`, `workflow.publish`, `agent.manage`, `node.manage`, `artifact.manage`) — **not** `connector.manage`, since connectors hold credentials/secrets and stay Admin-level, matching the old project's `credential.manage` placement.
- `adminGrants()` — workspace settings, member management, `connector.manage`, `api-key.manage`, `billing.manage`.
- `ownerGrants()` — just `workspace.delete`.

### `Authorization/WorkspaceContext.php`

Readonly value object holding `workspace` + `role`:

```php
final readonly class WorkspaceContext
{
    public function __construct(
        public Workspace $workspace,
        public ?Role $role,
    ) {}

    public function allows(Permission $permission): bool
    {
        return $this->role?->has($permission) ?? false;
    }

    public static function resolveRole(Workspace $workspace, User $user): ?Role
    {
        if ($workspace->owner_id === $user->id) {
            return Role::Owner;
        }

        return Context::getHidden("workspace:{$workspace->id}:user:{$user->id}:role")
            ?? tap(
                Cache::remember(
                    "workspace:{$workspace->id}:member:{$user->id}:role",
                    now()->addMinutes(5),
                    fn () => $workspace->members()->where('user_id', $user->id)->toBase()->value('role') ?? '__none__',
                ),
                fn ($raw) => Context::addHidden("workspace:{$workspace->id}:user:{$user->id}:role", $raw === '__none__' ? null : Role::from($raw)),
            );
    }

    public static function forget(int $workspaceId, int $userId): void
    {
        Cache::forget("workspace:{$workspaceId}:member:{$userId}:role");
        Context::forgetHidden("workspace:{$workspaceId}:user:{$userId}:role");
    }
}
```

Two layers of memoization, both necessary: a 5-minute `Cache` entry (avoids a DB hit on every permission check across requests) plus per-request `Context::addHidden`/`getHidden` memoization (avoids re-hitting the cache multiple times *within* one request — and deliberately not a static class property, which would leak state across requests under Octane or across tests in Pest). `toBase()->value('role')` bypasses the `Role` enum cast so a raw `'__none__'` sentinel string can represent "not a member" in the cache without the cast throwing on a non-enum value.

### `Http/Middleware/EnsureWorkspaceScope.php` (aliased `workspace.context` in `bootstrap/app.php`)

Resolves the `{workspace}` route-bound parameter, 404s if missing, resolves role via `WorkspaceContext::resolveRole()`, 403s if `null` (not a member), then binds the resolved context into the container:

```php
app()->instance(WorkspaceContext::class, new WorkspaceContext($workspace, $role));
```

### `Gate::before` global hook (`AppServiceProvider::configureGate()`)

```php
Gate::before(function (User $user, string $ability) {
    $permission = Permission::tryFrom($ability);
    if ($permission === null) {
        return null; // not one of our permission strings — let other gates/policies decide
    }
    if (! app()->bound(WorkspaceContext::class)) {
        return false; // no workspace resolved for this request — deny
    }
    return app(WorkspaceContext::class)->allows($permission) ?: null;
});
```

Every `Permission` case doubles as a Gate ability string, checkable anywhere with standard `Gate::allows()`/`$this->authorize()` — no Policy classes needed for workspace-scoped resources (the old project has zero `*Policy.php` files; this hook fully replaces them). This does **narrow** `STRUCTURE.md`'s "Policies are shared, not duplicated per surface" note — since Public API auth resolves straight to a `Workspace` via `ApiKey` with no per-user `Role`, this `Gate::before` hook (keyed on `User`) only applies to Internal requests. Public API authorization is instead the `ApiKey`'s own `abilities` list (see `docs/AUTH_PLAN.md`) — a deliberately separate, simpler check, not routed through `Role`/`Permission` at all.

### Base controller helpers (`Http/Controllers/Controller.php`)

```php
protected function requirePermission(Permission $permission): void
{
    $this->authorize($permission->value);
}

protected function ensureBelongsToWorkspace(Workspace $workspace, Model $model): void
{
    abort_if($model->workspace_id !== $workspace->id, 404);
}
```

Controllers call `$this->requirePermission(Permission::MemberInvite)` inline at the top of each action. `ensureBelongsToWorkspace()` guards nested resources bound by route-model-binding (e.g. a `{member}` that actually belongs to a different `{workspace}` than the one in the URL) — 404, not 403, so a workspace's existence/membership isn't leaked to a non-member probing IDs.

## Routing

Workspace-scoped route groups get `middleware(['auth:api', 'workspace.context'])`. Non-scoped workspace routes (`index`, `store` — listing/creating workspaces themselves) sit outside that group, since there's no `{workspace}` yet to resolve. Every other domain's route file (`workflows.php`, `agents.php`, `connectors.php`, `nodes.php`, `runs.php`, `triggers.php`, `artifacts.php`, `billing.php`) nests under `Route::prefix('workspaces/{workspace}')->middleware('workspace.context')`, consistent with `STRUCTURE.md`'s routes-as-folders convention.

## Folder structure summary

```
app/
  Models/
    Workspaces/
      Workspace.php
      WorkspaceMember.php
      WorkspaceInvitation.php
  Authorization/
    WorkspaceContext.php
  Services/
    Workspaces/
      WorkspaceService.php
      WorkspaceInvitationService.php
  Enums/
    Workspaces/
      Role.php
      Permission.php
  Http/
    Controllers/
      Api/
        Internal/
          V1/
            Workspaces/
              WorkspaceController.php          (index, store, show, update, destroy, updateAvatar)
              WorkspaceMemberController.php    (index, updateRole, destroy, leave)
              WorkspaceInvitationController.php (index, store, destroy, accept)
    Middleware/
      EnsureWorkspaceScope.php
  Observers/
    WorkspaceMemberObserver.php

database/
  migrations/
    ..._create_workspaces_table.php
    ..._create_workspace_members_table.php
    ..._create_workspace_invitations_table.php
```

## Verification

- `tests/Feature/Workspaces/{Create,UpdateMemberRole,RemoveMember,Leave,Invitation}Test.php`
- `tests/Unit/Authorization/WorkspaceContextTest.php` — owner resolves without a DB query for role; member role resolves and caches; `forget()` correctly busts both cache and request-memoized value after a role change.
- Manual: create workspace (confirm owner auto-membership row) → invite a member with `Editor` → accept invitation → confirm `Editor` can `workflow.manage` but not `connector.manage` → change their role to `Admin` mid-session → confirm the permission change takes effect on their *next* request (proving `WorkspaceMemberObserver` busts the cache) without needing them to log out.
