# Super Admin API: Plan

The Super Admin API is the back-office surface for operating the platform: looking up users and workspaces, handling billing exceptions, inspecting runs, toggling catalog items, and reviewing an audit trail. It is a **third API surface**, built apart from Internal (the first-party React app) and Public (external developers + MCP).

This plan replaces the current stop-gap, where admin routes live inside Internal and "admin" is a boolean on `users` (`users.is_platform_admin`, `routes/api/internal/admin.php`, `EnsurePlatformAdmin`, `Admin` controller folder, `admin:grant` command).

> Status (branch `dev-admin`): **built and tested** (`php artisan test --compact --filter=SuperAdmin`: 191 tests passing).
> - Phases 0-6 of § 15 plus the Horizon/Pulse gate and the Bruno `SuperAdmin` folder: master flag and the five configs, own accounts/tokens/guard, two-factor sign-in, roles and abilities, step-up confirmation, audit-by-default, idempotency, account management, users, workspaces (suspend, members), billing (credits, comped plan time, refunds), runs (list, retry, cancel, failure summary), catalog (connectors, custom nodes, templates), stats, referrals and the audit log API.
> - The old flag-based admin is gone: `users.is_platform_admin`, `EnsurePlatformAdmin`, `config/platform_admin.php`, the `admin:*` commands, `AdminAuditLog` and the `/api/v1/admin/*` routes. The create migrations were edited in place (pre-launch), so an existing local database needs `php artisan migrate:fresh`.
> - Added after launch review: failed Stripe webhooks are now recorded (`stripe_webhook_failures`), listed at `GET /billing/webhooks/failures` and replayable at `POST /billing/webhooks/{eventId}/replay` (ability `billing.replay-webhooks`: owner, finance); `POST /users/{id}/resend-verification`.
> - Model operations: the AI models are managed from `/models` (catalog, routes with a test button, prices, the operator's own endpoints, retirement, and read-only health / providers / workspace-key / usage / margin views). Abilities `models.view` (every role), `models.manage` (owner, operator), `models.prices` (owner, finance). Prices moved from `config/billing.php` into the `model_prices` table (the config map stays as a fallback); `php artisan model-catalog:doctor` runs the same health report, and `model-catalog:retire-due` runs daily.
> - Protection: **(1) spend guard**: `model_usage_daily` is rebuilt hourly (`model-usage:rollup`) and `model-spend:check` alerts when the day's provider spend nears or passes `MODEL_DAILY_BUDGET_USD`, or a workspace passes `MODEL_WORKSPACE_DAILY_USD` or spends many times its weekly average (alerts only, once a day each; also shown in `/models/health`). **(2) provider kill switch**: `POST /models/providers/{provider}/pause|resume` stops the platform using a provider on every route at once (workspaces' own keys keep working; a call with nothing left to run on answers 503). **(3) tamper-evident audit log**: every entry is chained (sequence, previous hash, HMAC keyed from `APP_KEY`); `GET /audit-log/integrity` and the daily `super-admin:audit-verify` detect an entry edited, removed or slipped in and alert; keep `head_hash` elsewhere to also catch a cut-off tail. **(4) `super-admin:lockdown`** ends every super admin session at once.
> - Pricing manager: plans, credit packs, billing-interval switches and discount codes are managed from `/pricing` (overview, plans CRUD with archive/duplicate/reorder, an impact preview, price changes, Stripe sync, packs, intervals, coupons, change history). Abilities `pricing.view` (every role), `pricing.manage` (owner, finance), `pricing.prices` (owner, finance). A new amount makes a new Stripe price; the old one is archived and kept in `plan_price_history` so `Plan::findByStripePriceId()` still maps existing subscribers to their plan, and they keep paying the old amount. The default plan can't be deactivated, archived or deleted; a plan with subscriptions, grants, usage or referral rules can only be archived. Packs and interval switches moved from `config/billing.php` into `credit_pack_definitions` / `billing_settings` (config stays the fallback), and `PlansDataset` now only creates missing plans so it no longer overwrites admin edits.
> - Own-model capacity: an operator endpoint can carry `workspace_requests_per_minute` and `total_requests_per_minute`. Enforced in `ByokProviderRegistrar::apply()` (every model call passes through it) by `EndpointRateLimiter`: a limited hop is skipped so the call fails over to the next provider, and with none left it answers 429 + `Retry-After` (`ModelBusyException`). Only the operator's own endpoints are limited; a workspace's own key is not your capacity. Calls turned away are counted per day (`rejected_today`, and an info finding in `/models/health`).
> - Models can be limited to a plan (`min_plan_id`, compared by monthly credits); the picker shows `plan_required` and the plan that would unlock it.
> - Found by running the suite on Postgres (production's database): a self-referencing foreign key in the `runs` and `super_admins` create migrations failed on a fresh database, and `LIKE` searches were case-sensitive. Both fixed. The remaining Postgres-only failures in the wider suite are older tests that use `"1"` as a UUID.
> - Still open: impersonation (§ 11.12), the system endpoints (§ 11.11), a frontend, and the choices in § 14 that were settled by default.
> - Decisions taken while building: own opaque tokens instead of Passport (§ 5.1); suspending a user revokes their Passport tokens and `AuthService` refuses new sessions; a suspended workspace is refused by `EnsureWorkspaceScope` and `CreditGate`; Horizon/Pulse are gated by `SUPER_ADMIN_DASHBOARD_IPS` (empty = nobody outside `local`); `referral_rewards.granted_by` is a plain uuid (no foreign key: the table predates `super_admins`); a super admin acting on a workspace appears in its audit trail as `super_admin:<email>`; the generic audit fallback records successful changes and denied (403) attempts, not validation failures.
---

## 1. Goals and principles

1. **Separate identity.** Super admins are their own accounts in their own table. A user, however privileged in a workspace, is never a super admin, and a super admin token is never a user token.
2. **Separate surface.** Own URL prefix, route files, controllers, requests, resources, services, middleware, config, models, tests, and Bruno folder. Nothing in `Api/Internal` or `Api/Public` imports from `Api/SuperAdmin`, and the reverse is limited to shared domain services/models (§ 3.2).
3. **Secure by default.** Closed unless switched on, mandatory 2FA, short-lived tokens, least privilege by role, every write audited, every failure observable.
4. **Boring and organised.** One file per domain, one controller per resource, thin controllers over services, predictable names, one test file per endpoint group.
5. **Operable without the API.** Everything critical (create/disable an admin, reset 2FA, read the audit log) also works from artisan, so a locked-out or compromised API never blocks recovery.
6. **Small blast radius.** The API can be disabled with one env var, deployed on its own host, and fenced by network rules, without code changes.

Non-goals: a frontend (separate repo, later), an admin UI for the model catalog (stays operator-fixed, see the model catalog design memory), impersonation in v1.

---

## 2. Architecture

```
Super Admin frontend (separate repo)
        │  HTTPS, own origin (CORS-restricted)
        ▼
/api/super-admin/v1/*     guard: super-admin (Passport, provider: super_admins)
        │  middleware: throttle → auth → super.admin (enabled, active, 2FA, IP) → permission:<ability>
        ▼
SuperAdmin controllers  →  SuperAdmin services  →  domain models/services (Billing, Runs, Workspaces…)
        │                          │
        └── audit (always) ────────┴──▶ super_admin_audit_logs
```

- **URL prefix:** `/api/super-admin/v1/...` (mirrors `/api/public/v1`).
- **Guard:** `super-admin`, Passport driver, user provider `super_admins`.
- **Same codebase, same database**, with a deployment option to serve it from an admin-only host (§ 9).

---

## 3. Folder layout

### 3.1 Tree

```
app/
├── Console/Commands/SuperAdmin/
│   ├── CreateSuperAdminCommand.php         super-admin:create
│   ├── DisableSuperAdminCommand.php        super-admin:disable
│   ├── EnableSuperAdminCommand.php         super-admin:enable
│   ├── ResetSuperAdminTwoFactorCommand.php super-admin:reset-2fa
│   ├── RevokeSuperAdminTokensCommand.php   super-admin:revoke-tokens
│   └── SuperAdminAuditLogCommand.php       super-admin:audit-log
├── Enums/SuperAdmin/
│   ├── SuperAdminRole.php                  Owner, Operator, Finance, Support, Viewer
│   └── SuperAdminAbility.php               users.view, billing.grant-credits, …
├── Http/
│   ├── Controllers/Api/SuperAdmin/V1/
│   │   ├── Auth/            AuthController, TwoFactorController, SessionController, ProfileController
│   │   ├── Accounts/        SuperAdminAccountController           (manage other super admins)
│   │   ├── Users/           UserController, UserSessionController
│   │   ├── Workspaces/      WorkspaceController, WorkspaceMemberController
│   │   ├── Billing/         SubscriptionController, CreditController, PlanGrantController, RefundController
│   │   ├── Runs/            RunController, RunActionController
│   │   ├── Catalog/         NodeController, ConnectorController, TemplateController
│   │   ├── Referrals/       (moved from Admin/Referrals, unchanged behaviour)
│   │   ├── Stats/           StatsController
│   │   ├── System/          HealthController, FeatureFlagController (later)
│   │   └── AuditLog/        AuditLogController
│   ├── Middleware/SuperAdmin/
│   │   ├── EnsureSuperAdminApiEnabled.php
│   │   ├── EnsureSuperAdminIsActive.php
│   │   ├── EnsureSuperAdminTwoFactor.php
│   │   ├── EnsureSuperAdminIpAllowed.php
│   │   ├── EnsureSuperAdminAbility.php     alias: super-admin.can:<ability>
│   │   └── AuditSuperAdminRequest.php
│   ├── Requests/Api/SuperAdmin/V1/…        one Form Request per write endpoint
│   └── Resources/Api/SuperAdmin/V1/…       one Resource per returned shape
├── Models/SuperAdmin/
│   ├── SuperAdmin.php
│   ├── SuperAdminAuditLog.php
│   └── SuperAdminLoginEvent.php
├── Notifications/SuperAdmin/…              new-IP login, lockout, 2FA reset
├── Policies/SuperAdmin/…                   only if a model needs more than ability checks
└── Services/SuperAdmin/
    ├── Auth/                SuperAdminAuthService, SuperAdminTwoFactorService, SuperAdminLoginThrottle
    ├── Audit/               SuperAdminAuditLogger
    ├── Users/ Workspaces/ Billing/ Runs/ Catalog/ Referrals/ Stats/
config/
├── super_admin.php             master flag, host, URL prefix (§ 7.8)
├── super_admin_auth.php        token/refresh TTLs, lockout, password rules
├── super_admin_security.php    IP allowlist, CORS, rate limits, step-up caps, idempotency
├── super_admin_roles.php       role → ability matrix
└── super_admin_alerts.php      which events alert, and where
database/
├── factories/SuperAdmin/SuperAdminFactory.php
├── seeders/SuperAdminSeeder.php            local/dev only
└── migrations/…create_super_admins_table, …create_super_admin_audit_logs_table, …login_events
routes/api/super_admin/
├── index.php   auth.php   accounts.php   users.php   workspaces.php   billing.php
├── runs.php    catalog.php   referrals.php   stats.php   audit_log.php   system.php
tests/Feature/SuperAdmin/
├── Auth/ Accounts/ Users/ Workspaces/ Billing/ Runs/ Catalog/ Referrals/ Stats/ AuditLog/
└── Security/   (boundary, abilities, audit-coverage, kill-switch, IP allowlist)
Bruno collection: SuperAdmin/ (mirrors the route files)
```

### 3.2 Boundary rules

- Controllers, Requests, Resources, Middleware, Commands and Services named *SuperAdmin* are used **only** by this surface.
- Super Admin services may call existing domain code (`CreditMeter`, `PlanLimitGate`, run/cancel services) and read domain models. They must not be called from Internal or Public.
- Reuse is by composition, not by editing domain code to know about admins. If a domain action needs an "actor", pass a neutral value (`reason`, `performedBy` string), not a `SuperAdmin` model.
- An architecture test (Pest `arch()`) enforces: nothing under `App\Http\Controllers\Api\Internal` or `...\Public` depends on `App\*\SuperAdmin`.

---

## 4. Data model

### 4.1 `super_admins`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid pk | |
| `name` | string | |
| `email` | string unique | stored lowercased |
| `password` | string | hashed; no signup, set by `super-admin:create` or an invite flow |
| `role` | string (enum) | `owner`, `operator`, `finance`, `support`, `viewer` |
| `abilities_override` | json nullable | optional per-account extra/removed abilities (v2; omit in v1 if unneeded) |
| `two_factor_secret` | text encrypted nullable | |
| `two_factor_recovery_codes` | text encrypted:array nullable | |
| `two_factor_confirmed_at` | timestamp nullable | |
| `allowed_ips` | json nullable | CIDR list; null = no per-account restriction |
| `password_changed_at` | timestamp nullable | drives forced rotation |
| `failed_login_count` | unsigned int default 0 | |
| `locked_until` | timestamp nullable | |
| `last_login_at`, `last_login_ip` | nullable | |
| `disabled_at` | timestamp nullable | soft off-switch; tokens revoked when set |
| `created_by` | uuid nullable → `super_admins.id` | null = created from CLI |
| `remember_token` | none | not needed, token-only |
| timestamps | | |

No `email_verified_at` and no social login: accounts are provisioned deliberately.

### 4.2 `super_admin_audit_logs` (append-only)

`id`, `super_admin_id` (nullable for CLI/system, indexed), `actor_label` (e.g. `cli:jaydeep`), `action` (dotted, e.g. `billing.credits.granted`), `subject_type`, `subject_id`, `before` json, `after` json, `reason` text nullable, `ip_address`, `user_agent`, `request_id`, `created_at`. No `updated_at`; no update/delete path in code. Indexes on `(subject_type, subject_id)`, `(super_admin_id, created_at)`, `action`.

### 4.3 `super_admin_login_events`

`id`, `super_admin_id` nullable, `email_attempted`, `event` (`login_succeeded`, `login_failed`, `two_factor_failed`, `locked_out`, `token_refreshed`, `logout`, `ip_blocked`), `ip_address`, `user_agent`, `created_at`. Feeds new-IP alerts and brute-force detection.

### 4.4 What is removed

- `users.is_platform_admin` (and `User::isPlatformAdmin()`).
- `admin_audit_logs` (replaced by 4.2). Pre-launch, edit the original create migrations instead of adding drop/rename migrations (confirm no environment holds data first).

Factories: `SuperAdminFactory` with states `owner()`, `operator()`, `finance()`, `support()`, `viewer()`, `withTwoFactor()`, `disabled()`, `locked()`.

---

## 5. Authentication

### 5.1 Guard and tokens

- `config/auth.php`: provider `super_admins` (Eloquent → `SuperAdmin`), guard `super-admin` (driver `super-admin-token`, registered in `SuperAdminServiceProvider`). Routes use `auth:super-admin`.
- **Decision: Super Admin tokens are not Passport.** They are opaque random strings issued by `SuperAdminTokenService` and stored (SHA-256 hashed) in `super_admin_tokens`. Reasons: a second Passport provider needs its own OAuth client row (created with `passport:client`, a manual step per environment), the user API's password grant is wired through one shared client, and a table of our own makes isolation absolute: a user JWT can never validate here, and a Super Admin token is just an unparseable string to Passport. The guard (`SuperAdminGuard`) caches its result on the request, not on itself.
- Token scope is a column, not an OAuth scope: `full` (only after a two-factor code) or `setup` (password change and two-factor enrolment only, no refresh token). `EnsureSuperAdminTwoFactor` rejects anything but a full token.
- **Access token TTL 15 min, refresh token 7 days, sliding but with an absolute cap of 30 days.** A refresh rotates the refresh token; reuse of an old refresh token revokes the whole session family.
- No personal access tokens, no API keys, no long-lived tokens for super admins.

### 5.2 Login flow

1. `POST /auth/login` (email + password). Throttled per IP and per email. On success:
   - 2FA confirmed → returns `requires_two_factor: true` + a short-lived (5 min), single-use `challenge_token`. **No access token yet.**
   - 2FA not confirmed → returns a **restricted setup token** (scope `super-admin:setup`) that is valid only for the 2FA enable/confirm endpoints and `me`.
2. `POST /auth/2fa/verify` (challenge token + TOTP or recovery code) → full access + refresh token.
3. `POST /auth/refresh`, `POST /auth/logout`, `POST /auth/logout-all`.

Generic error messages on failure (no account enumeration). Failed attempts increment `failed_login_count`; 5 failures → `locked_until` 15 min (escalating); each event written to `super_admin_login_events`.

### 5.3 2FA

- TOTP (the app's `TwoFactorAuthService` is the model to follow; extract a shared contract or write a thin `SuperAdminTwoFactorService`, but do not fork the crypto, keep one implementation of verification).
- Mandatory: `EnsureSuperAdminTwoFactor` blocks every route except the setup ones until `two_factor_confirmed_at` is set. There is no config to switch it off outside `local`.
- 8 single-use recovery codes, regenerable (re-auth with password + TOTP). Using a recovery code triggers a notification.
- Reset only via CLI (`super-admin:reset-2fa`) or by an `owner` through the accounts endpoint, both audited.

### 5.4 Passwords

Min 14 chars, checked against the compromised-password rule (`Password::uncompromised()`), `password_changed_at` enforces rotation (default 90 days, configurable; expired → only `change-password` works). Change-password revokes all other sessions.

---

## 6. Authorization (roles and abilities)

Roles are an enum on the account; abilities are an enum in code (no tables, easy to review in a PR). Middleware `super-admin.can:<ability>` guards each route group; controllers don't re-check.

| Ability group | Owner | Operator | Finance | Support | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| `stats.view`, `audit.view`, `users.view`, `workspaces.view`, `runs.view`, `billing.view` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `users.suspend`, `users.force-logout`, `workspaces.suspend` | ✓ | ✓ | | ✓ | |
| `runs.retry`, `runs.cancel` | ✓ | ✓ | | ✓ | |
| `catalog.manage` (nodes, connectors, templates) | ✓ | ✓ | | | |
| `referrals.manage` | ✓ | ✓ | ✓ | | |
| `billing.grant-credits`, `billing.extend-plan`, `billing.refund` | ✓ | | ✓ | | |
| `accounts.manage` (create/disable/role/2FA-reset other admins) | ✓ | | | | |
| `system.manage` | ✓ | ✓ | | | |

Rules: only an `owner` can manage accounts; at least one active `owner` must always exist (guarded in the service and tested); nobody can change their own role or disable themselves; high-risk actions (refund, credit grant above a threshold, account changes) require a `reason` and re-confirm the TOTP (§ 7.4).

### 6.1 Where roles live
- **Roles** are a fixed enum, `SuperAdminRole` (`owner`, `operator`, `finance`, `support`, `viewer`); each account has exactly one, in `super_admins.role`.
- **Abilities** are a fixed enum, `SuperAdminAbility`.
- The **matrix** lives in `config/super_admin_roles.php`, in code: changing it takes a pull request, review and deploy.
- No roles table and no custom roles in v1, so the matrix is easy to audit and cannot be edited through a compromised admin account.

### 6.2 Managing accounts and roles
Only an `owner` has `accounts.manage`. Every action below needs a `reason` and a fresh TOTP (**S**) and is audited with before/after.

| Action | Endpoint | Rules |
|---|---|---|
| Create admin | `POST /accounts` | owner only; initial role chosen, default `viewer` |
| Change role | `POST /accounts/{id}/role` | owner only |
| Disable / enable | `POST /accounts/{id}/disable`, `/enable` | disabling revokes all tokens |
| Reset 2FA | `POST /accounts/{id}/reset-2fa` | owner only |
| List / view | `GET /accounts`, `/accounts/{id}` | owner only |

Guard rules (enforced in the service, tested):
1. At least one active owner always exists; demoting or disabling the last owner returns 409.
2. You cannot change your own role or disable yourself; another owner must.
3. You cannot grant a role above your own, so only owners create owners.
4. The first owner comes from the CLI: `super-admin:create --role=owner`.
5. Accounts are never hard-deleted; the audit history keeps pointing at them.

### 6.3 Enforcement per request
- Routes declare the ability: `->middleware('super-admin.can:billing.refund')`.
- `EnsureSuperAdminAbility` reads the role from the **database account on every request**, not from the token, so a role change or disable takes effect on the next request.
- Controllers do not re-check roles; one place enforces access.
- `GET /auth/me` returns the role and ability list for the frontend to hide controls; the backend remains the real gate.

### 6.4 Role changes in practice
- **Any demotion** revokes the admin's refresh tokens (they log in again with the new role); an owner → non-owner change revokes all their sessions.
- **Offboarding** = disable (tokens revoked immediately, history kept).
- Every role change, account creation and disable sends an alert (`config/super_admin_alerts.php`) and emails the affected admin.
- **Break-glass via CLI** (needs server access and the master flag on): `super-admin:create`, `:enable`, `:disable`, `:reset-2fa`, and an emergency `:set-role`, all audited with actor `cli:<os-user>`.
- **Later, not v1:** per-account ability overrides (`abilities_override`), two-person approval above a threshold, time-boxed elevation.

### 6.5 How this differs from user roles
| | Users | Super admins |
|---|---|---|
| Account | `users` table | `super_admins` table |
| Role scope | per workspace (membership role); one person can be owner in one workspace and member in another | global; one role per account across the whole platform |
| Controls | what you can do in your own workspaces | what you can do across all workspaces, users and billing |
| Assigned by | workspace owners/admins via invitations | a super admin owner, or the CLI |
| Sign-up | self-service, plus social login | none; deliberately provisioned |
| Defined in | workspace role logic in the Internal API | enum + `config/super_admin_roles.php` |
| Token | user Passport token | super admin token on a separate guard; neither works on the other's routes |
| 2FA | optional | mandatory |

The two never mix: a workspace owner has no platform power, and a super admin has no workspace role. Super admins act on workspaces only through this API, audited. No code or tables are shared between the two role systems.

---

## 7. Security controls

### 7.1 Closed by default: one master flag
`SUPER_ADMIN_ENABLED` is the **only** on/off switch for the whole Super Admin panel and defaults to **false**. Nothing else has its own enable/disable flag. Public deploys leave it off. See § 7.8 for exactly what "off" means.

### 7.2 Network
- Optional host split: serve the prefix only on `super-admin-api.<domain>` (route `domain()` constraint from `config('super_admin.host')`); the main host answers 404 for it.
- Per-account `allowed_ips` plus a global `SUPER_ADMIN_ALLOWED_IPS` allowlist. A blocked request is logged as `ip_blocked`.
- Recommended outside the app: Cloudflare Access / VPN in front of the admin host.
- CORS for this prefix allows **only** the Super Admin frontend origin (`SUPER_ADMIN_ALLOWED_ORIGINS`), no wildcards, no credentials cookies (token auth only).

### 7.3 Rate limiting
Named limiters in `AppServiceProvider`: `super-admin-auth` (5/min per IP + email), `super-admin` (120/min per admin), `super-admin-sensitive` (10/min per admin for refunds, credit grants, account changes).

### 7.4 Step-up confirmation
Sensitive endpoints require a `reason` (min 10 chars) and a fresh `totp_code` in the body, verified in the Form Request. The reason and the fact of confirmation go into the audit row. Sensitive set: refunds, credit grants/revocations, plan extensions, account create/disable/role change/2FA reset, suspend workspace, and (when built) impersonation.

### 7.5 Input and output safety
- Form Requests everywhere; no mass-assigning request input; explicit `Fillable`.
- Resources never expose secrets: password hashes, 2FA data, tokens, connector credentials, secrets-vault values, AI provider keys. A test serialises every Resource against a "forbidden keys" list.
- Search inputs are bound parameters; sort columns are whitelisted; page size capped at 100.
- Security headers via the existing `SecurityHeaders` middleware; responses carry `Cache-Control: no-store`.

### 7.6 Monitoring and alerts
Use `config/admin_alerts.php` (fixed recipients, not User models, so alerts work during DB incidents). Alerts on: login from a new IP, lockout, 2FA reset, recovery-code use, role change, account created/disabled, refund/credit grant over the threshold, repeated `ip_blocked`. Pulse/Horizon access is a separate gate (see § 14, open decision 2).

### 7.7 Secrets and config
No secrets in the repo. All Super Admin settings live in the five dedicated config files (§ 3.1, § 7.8) and nothing outside the SuperAdmin code reads them. Every value has a safe default (closed, 2FA on, short TTLs) and an env override, except security requirements, which are **not togglable**: 2FA, the audit trail and step-up confirmation cannot be switched off by env outside `local`.

### 7.8 Master flag and separate configs

**`SUPER_ADMIN_ENABLED` (default `false`)**, read only through `config('super_admin.enabled')`, never `env()` in code, so it works with `config:cache`. When off:

- **Routes are not registered.** `routes/api/super_admin/index.php` returns on its first line when the flag is off, so the URLs do not exist and `route:list` shows no `super-admin.*` routes (stronger than answering 404).
- **Auth is dead.** The `super-admin` guard rejects every token.
- **CLI commands refuse to run** with "Super Admin is disabled", including `super-admin:create`. Enabling is a deliberate act, locally too.
- **Side effects are off:** super admin alerts, scheduled tasks, and any Horizon/Pulse gate that depends on super admins.
- **Data is untouched.** Tables and migrations stay, so turning the flag back on loses nothing.

**Separate configs**, one job per file, flat names:

| File | Contents |
|---|---|
| `config/super_admin.php` | master flag, optional API host, URL prefix |
| `config/super_admin_auth.php` | access/refresh TTLs, lockout thresholds, password rules, rotation days, challenge-token lifetime |
| `config/super_admin_security.php` | global IP allowlist, CORS origins, rate limits, step-up caps (credit/refund), idempotency TTL |
| `config/super_admin_roles.php` | the role → ability matrix from § 6 |
| `config/super_admin_alerts.php` | which events alert and where they go (kept apart from `admin_alerts.php`) |

---

## 8. Audit

- **Everything that changes state is audited, automatically.** `AuditSuperAdminRequest` middleware wraps every non-GET request: it records actor, route name, method, target (if resolvable), request id, IP, UA, status. Services add the precise `before`/`after` diff and the `reason` via `SuperAdminAuditLogger` (`record()`, `recordChanges()`, kept from the current `AdminAuditLogger`, retargeted at `SuperAdmin`).
- Action naming: `<domain>.<resource>.<verb>` e.g. `billing.credits.granted`, `workspaces.suspended`, `accounts.role_changed`.
- Reads of sensitive data (a user's PII detail page, a workspace's members) record `*.viewed` rows so "who looked at this" is answerable.
- Log failures must **fail the request** for sensitive writes (audit and change in one transaction).
- Immutable: no update/delete route, no model delete; the DB user for the app may be denied `UPDATE/DELETE` on this table where the host allows it.
- A feature test walks the route collection and fails if any non-GET super-admin route lacks the audit middleware or isn't in an explicit allowlist (login/refresh).
- Retention: keep indefinitely (small table); export endpoint/command for compliance.

---

## 9. Deployment

| Option | When |
|---|---|
| **A. Same app, flag off on public, on for private deploy** (recommended) | Same image, second environment with `SUPER_ADMIN_ENABLED=true`, optional admin host + IP allowlist/Cloudflare Access. |
| B. Same app, same host | Fine early on; rely on the master flag, scopes, 2FA. |
| C. Separate backend app | Not recommended: duplicates models/migrations and bypasses domain rules. |

Admin-host deploy additionally: its own DB user with minimum grants where possible, `SESSION`/cookie irrelevant (token auth), Horizon/Pulse reachable only from this host.

---

## 10. API conventions

- Base: `/api/super-admin/v1`. JSON only.
- **Envelope: the existing `ApiResponse`**: `{ success, statusCode, message, data }`, with `meta` for pagination (`paginated()` for offset lists, `cursorPaginated()` for large/append-only lists like the audit log and runs). Errors use the existing exception mapping in `bootstrap/app.php`.
- Auth header: `Authorization: Bearer <token>`.
- IDs are UUIDs; route params constrained with `whereUuid`.
- Lists: `?q=`, `?filter[...]=`, `?sort=-created_at`, `?per_page=` (≤100), `?cursor=`.
- Writes accept `reason` where marked **R**; sensitive writes also take `totp_code` (**S**).
- Idempotency: money-moving endpoints (credit grant, refund) accept an `Idempotency-Key` header, stored with the audit/credit transaction so retries don't double-apply.
- Status codes: 200/201/204, 401 unauthenticated, 403 wrong ability / 2FA not confirmed / IP blocked, 404 not found, 409 state conflict, 422 validation, 429 throttled.
- Versioning: `v1` in the path; breaking changes ship as `v2`.

---

## 11. Endpoint catalogue

Ability in brackets. **R** = reason required, **S** = reason + fresh TOTP. All routes are named `super-admin.<group>.<action>`.

### 11.1 Auth: `routes/api/super_admin/auth.php`
| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | throttle `super-admin-auth`; returns challenge or setup token |
| POST | `/auth/2fa/verify` | challenge token + TOTP/recovery code → tokens |
| POST | `/auth/refresh` | rotates refresh token; reuse → revoke family |
| POST | `/auth/logout` · `/auth/logout-all` | |
| GET | `/auth/me` | profile, role, abilities, 2FA state |
| POST | `/auth/change-password` | revokes other sessions |
| POST | `/auth/2fa/enable` · `/2fa/confirm` | work with the setup token |
| GET/POST | `/auth/2fa/recovery-codes`, `/recovery-codes/regenerate` | regenerate needs password + TOTP |
| GET | `/auth/sessions` · DELETE `/auth/sessions/{id}` | own sessions |
| GET | `/auth/events` | own login events |

### 11.2 Accounts: `accounts.php` [`accounts.manage`, owner only]
| Method | Path | Notes |
|---|---|---|
| GET | `/accounts` · `/accounts/{id}` | |
| POST | `/accounts` | **S**; creates account, emails a one-time setup link or returns a temp password once |
| PATCH | `/accounts/{id}` | name, `allowed_ips` |
| POST | `/accounts/{id}/role` | **S**; keeps ≥1 active owner |
| POST | `/accounts/{id}/disable` · `/enable` | **S**; disable revokes tokens |
| POST | `/accounts/{id}/reset-2fa` | **S** |
| POST | `/accounts/{id}/revoke-sessions` | **R** |
| GET | `/accounts/{id}/login-events` | |

### 11.3 Users: `users.php`
| Method | Path | Ability |
|---|---|---|
| GET | `/users` (search by email/name/id, filter by status/plan/created range) | `users.view` |
| GET | `/users/{id}` (profile, workspaces, plan, last activity; no secrets) | `users.view` (audited as viewed) |
| GET | `/users/{id}/sessions` · `/events` | `users.view` |
| POST | `/users/{id}/suspend` · `/unsuspend` | `users.suspend` **R** |
| POST | `/users/{id}/force-logout` (revokes all Passport tokens) | `users.force-logout` **R** |
| POST | `/users/{id}/resend-verification` | `users.suspend` |

Suspension needs a `users.suspended_at`/`suspended_reason` pair (or equivalent) and a check in the Internal auth path; define this when building, with its own migration/test.

### 11.4 Workspaces: `workspaces.php`
| Method | Path | Ability |
|---|---|---|
| GET | `/workspaces` (search, plan, status) · `/workspaces/{id}` | `workspaces.view` |
| GET | `/workspaces/{id}/members` · `/usage` · `/audit-log` | `workspaces.view` |
| POST | `/workspaces/{id}/suspend` · `/unsuspend` | `workspaces.suspend` **R** |
| DELETE | `/workspaces/{id}/members/{memberId}` | `workspaces.suspend` **S** |

### 11.5 Billing: `billing.php`
| Method | Path | Ability |
|---|---|---|
| GET | `/workspaces/{id}/subscription` · `/credits` · `/credit-transactions` · `/plan-grants` | `billing.view` |
| POST | `/workspaces/{id}/credits/grant` | `billing.grant-credits` **S**, idempotent, amount cap from config |
| POST | `/workspaces/{id}/credits/revoke` | `billing.grant-credits` **S**, idempotent |
| POST | `/workspaces/{id}/plan-grants` (extend trial/plan to a date) | `billing.extend-plan` **S** |
| DELETE | `/plan-grants/{id}` | `billing.extend-plan` **S** |
| POST | `/workspaces/{id}/refunds` (Stripe refund via Cashier) | `billing.refund` **S**, idempotent |
| GET | `/billing/plans` · `/credit-packs` | `billing.view` |

Uses existing `CreditMeter`, `PlanGrant`, `CreditTransaction`, `Subscription`; no billing logic is re-implemented here.

### 11.6 Runs and agents: `runs.php`
| Method | Path | Ability |
|---|---|---|
| GET | `/runs` (cursor; filter status/workspace/date/error) · `/runs/{id}` · `/runs/{id}/node-runs` | `runs.view` |
| POST | `/runs/{id}/retry` · `/cancel` | `runs.retry` / `runs.cancel` **R** |
| GET | `/runs/failures/summary` (top errors, by node/connector) | `runs.view` |

### 11.7 Catalog: `catalog.php` [`catalog.manage`]
| Method | Path |
|---|---|
| GET | `/catalog/nodes` · `/catalog/connectors` · `/catalog/templates` (+ `{id}`) |
| POST | `/catalog/nodes/{id}/disable` · `/enable` (**R**) |
| POST | `/catalog/connectors/{id}/disable` · `/enable` (**R**) |
| POST | `/catalog/templates/{id}/publish` · `/unpublish` (**R**) |

Out of scope by design: the model catalog (operator-fixed).

### 11.8 Referrals: `referrals.php` [`referrals.manage`]
Moved verbatim from `routes/api/internal/admin.php`: programs, rules (reorder/toggle), codes, referrals (reject/restore), rewards (manual, approve, grant-now, revoke), blocked domains, stats, simulate. Same controllers, new namespace; behaviour and tests unchanged.

### 11.9 Stats: `stats.php` [`stats.view`]
`GET /stats/overview` (sign-ups, activations, runs/day, success rate, credits consumed, revenue, active workspaces), `/stats/timeseries?metric=&from=&to=`, `/stats/top-workspaces` (by cost), `/stats/top-errors`. Cached 60 s.

### 11.10 Audit log: `audit_log.php` [`audit.view`]
`GET /audit-log` (cursor; filter actor, action, subject, date range) · `GET /audit-log/{id}`. Read-only.

### 11.10a Customer pages: `customers.php`

Built for the admin panel's customer profile (one page per user or workspace). Reads need that record's own view ability; the search answers only for the kinds the admin may view; writing a note or tag needs `customers.annotate` (operator, finance, support, owner).

| Method and path | What it gives |
|---|---|
| `GET search?q=` | users, workspaces and runs (by id or its first 8+ characters) matching the text, with tags; `skipped` names the kinds the role may not view. At least 2 characters. |
| `GET users/{id}/overview`, `GET workspaces/{id}/overview` | one read for the page header: tags, notes count, last admin action (views excluded), and for a workspace the billing summary, run counts (per day, 7 days vs the 7 before, last 24 hours, counted by the database), and refunds in 90 days. One audit row (`*.overview_viewed`). |
| `GET users/{id}/timeline`, `GET workspaces/{id}/timeline` | runs, credit transactions, plan grants and admin actions merged newest first. `before` (time), `types[]` (`run`, `credits`, `plan`, `admin`), `limit` (max 100); `next` is the `before` for the following page; `skipped` names sources the role may not read. A user's runs are the ones they started. "Viewed" audit rows are left out. One audit row (`*.timeline_viewed`). |
| `GET {users,workspaces}/{id}/annotations` | tags and notes, newest first. |
| `POST {users,workspaces}/{id}/notes` | add a note (2 to 2000 characters). The audit row holds the note id, not its text. |
| `DELETE {users,workspaces}/{id}/notes/{note}` | the author, or an owner. |
| `PUT {users,workspaces}/{id}/tags` | the complete list (max 8, 2 to 24 characters, lower-cased); audited only when it changes. |

Also: `users` and `workspaces` lists and details carry `tags` and take `?tag=`; `runs` takes `?user_id=` (the user who started it).

### 11.11 System: `system.php` [`system.manage`] (later)
`GET /system/health` (queues/Horizon summary, failed jobs count, Reverb), `POST /system/failed-jobs/{id}/retry`, feature flags if introduced.

### 11.12 Impersonation (v2, optional)
`POST /users/{id}/impersonate` **S**, requires reason ≥ 20 chars, issues a user token with a distinct `impersonated_by` claim, 15-minute TTL, read-only unless `write: true` and owner role, banner header for the frontend, audited at start and end, and Internal-API middleware refuses billing/security changes (password, email, 2FA, API keys) while impersonated. Do not build until the rest is stable.

---

## 12. Testing

Pest feature tests, factories only, `RefreshDatabase`. Minimum per endpoint: success, 401 unauthenticated, 403 wrong role, 422 validation, audit row written with correct diff.

Cross-cutting suites under `tests/Feature/SuperAdmin/Security/`:
- **Boundary:** a user token → 401 on every super-admin route; a super-admin token → 401 on every Internal route; token lacking the scope rejected.
- **Kill switch:** `SUPER_ADMIN_ENABLED=false` → no `super-admin.*` route is registered (inspect the route collection), the guard rejects tokens, every `super-admin:*` command refuses to run, and alerts/schedules are skipped.
- **Role management:** last-owner rule, no self role change or self-disable, no granting above own role, a role change applies on the very next request, demotion revokes refresh tokens, disabled accounts are rejected immediately.
- **2FA gate:** unconfirmed 2FA → 403 everywhere but setup endpoints.
- **Ability matrix:** data-driven (dataset of role × ability) asserts the table in § 6.
- **Last owner:** cannot demote/disable the last owner or yourself.
- **Audit coverage:** every non-GET route has audit middleware or is allowlisted.
- **Secrets leakage:** every Resource tested against forbidden keys.
- **Throttling/lockout**, refresh-token reuse revocation, IP allowlist, idempotency (double POST applies once), step-up (missing/invalid TOTP rejected).
- **Architecture (`arch()`):** Internal/Public cannot depend on SuperAdmin namespaces; SuperAdmin controllers don't query models directly where a service exists (soft rule).

Run with `php artisan test --compact --filter=SuperAdmin`; run Pint on dirty files.

---

## 13. Migration from today's code

1. Create tables, model, factory, guard/provider, Passport client, `super-admin:create`.
2. Move `AdminAuditLogController`, referral controllers/resources, `AdminAuditLogger`, `ReferralAdmin` into the SuperAdmin namespaces; retarget audit to `SuperAdmin`.
3. Update `bootstrap/app.php` (route registration: add `routes/api/super_admin/index.php`; aliases `super-admin.*`; remove `platform.admin`) and delete `routes/api/internal/admin.php`.
4. Remove `EnsurePlatformAdmin`, `config/platform_admin.php` (with `PLATFORM_ADMIN_API_ENABLED` and `PLATFORM_ADMIN_REQUIRE_2FA` in `.env.example`; replaced by `SUPER_ADMIN_ENABLED` and the five `super_admin*.php` configs), `GrantPlatformAdminCommand`, `RevokePlatformAdminCommand`, `AdminAuditLogCommand`, `users.is_platform_admin`, `User::isPlatformAdmin()`, and their migrations/tests; replace with the new equivalents.
5. Port `PlatformAdminAccessTest` and `AdminReferralApiTest` to `tests/Feature/SuperAdmin/…`; they must stay green.
6. Update the Bruno `Admin` folder → `SuperAdmin`; update `docs/LAUNCH_CHECKLIST.md` admin lines (150-155, 393) and `.env.example`.
7. Horizon (`HorizonServiceProvider::viewHorizon`) and a new Pulse gate: see open decision 2.

---

## 14. Open decisions (need an answer before building)

1. **Data in any environment?** If none, edit the original create migrations; otherwise add drop/rename migrations.
2. **Horizon/Pulse access.** They use the `web` guard and know nothing about token-auth super admins. Options: (a) IP allowlist / Cloudflare Access only, (b) a tiny session login for super admins on the admin host, (c) allowlist of emails in config. Recommendation: (a).
3. ~~**Passport second-provider approach**~~ **Resolved:** own opaque tokens, no Passport (§ 5.1).
4. **Per-account IP allowlist**: build in v1 or leave the column for later?
5. **Account onboarding**: one-time setup link by email vs. temp password printed by the CLI.
6. **Amount caps** for credit grants and refunds without a second approver; is a two-person approval needed later?

Defaults assumed in this plan (change if you disagree): CLI commands also refuse to run when `SUPER_ADMIN_ENABLED` is off; the five-file config split in § 7.8; any demotion revokes the admin's refresh tokens.

---

## 15. Delivery phases

| Phase | Scope | Exit criteria |
|---|---|---|
| **0. Foundations** | tables, model, factory, config, guard/provider/client, CLI commands, kill switch, boundary + arch tests | `super-admin:create` works; user/admin token isolation tests green |
| **1. Auth** | login, 2FA, refresh, logout, sessions, change password, lockout, login events, alerts | full auth test suite green; setup-token flow works |
| **2. Move existing** | audit log, referrals, remove old flag/middleware/commands | old admin tests ported and green; nothing references `platform_admin` |
| **3. Security layer** | abilities/roles middleware, audit middleware, limiters, CORS, IP allowlist, step-up, idempotency helper | matrix, audit-coverage, leakage suites green |
| **4. Read APIs** | stats, users, workspaces, runs, billing reads | pagination + search tested |
| **5. Write APIs** | suspend/unsuspend, force-logout, credits, plan grants, refunds, run retry/cancel, catalog toggles | all **R**/**S** rules tested, audit diffs asserted |
| **6. Accounts** | manage other super admins | last-owner rule tested |
| **7. Hardening** | host split, deploy docs, Bruno collection, launch-checklist updates, pen-test pass over the boundary | checklist items ticked |
| **8. Later** | impersonation, system endpoints, approvals | separate plan |

## 16. Definition of done

- Every endpoint in § 11 (phases 1-6) implemented with Form Request, Resource, service, route name, Pest tests, and a Bruno request.
- All suites in § 12 pass; Pint clean.
- With `SUPER_ADMIN_ENABLED=false` no Super Admin route exists, tokens are rejected, and the commands refuse to run.
- No reference to `is_platform_admin` / `platform.admin` remains.
- Launch-checklist admin items verified against the real admin deployment.
