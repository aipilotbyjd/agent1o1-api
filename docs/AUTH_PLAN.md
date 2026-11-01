# Auth Setup Plan

## Context

This plan consolidates every auth decision for the Gumloop-style workflow/agent SaaS (see `PLAN.md`/`STRUCTURE.md`). The API is split into two surfaces — **Internal** (the first-party React canvas) and **Public** (external developers + the MCP server) — and auth is decided per-surface, not as one global mechanism. See `STRUCTURE.md`'s "Public vs. Internal API" section for the full rationale.

**Revision note**: this supersedes an earlier version of this doc that specced Sanctum for Internal auth. That was changed to Passport (Password Grant) — Passport was already installed and migrated in this project. It's also now grounded in the old project (`agent-1o1-api`), which already built and ran this exact Passport+Socialite+2FA design — proven patterns from there are called out explicitly below instead of re-inventing them.

## Decision: Passport (Internal, Password Grant) + a bespoke `ApiKey` model (Public)

- **Internal API** — Laravel Passport, using the **Password Grant** (`grant_type=password`): the frontend exchanges email/password for a short-lived access token + long-lived refresh token, sent as `Authorization: Bearer` on every request. Google/GitHub logins bypass the password grant (no password exists to grant against) and instead get a Passport **Personal Access Token** — long-lived, no refresh token. Both are Bearer tokens used identically after login, but only password-grant tokens expire/refresh; the frontend needs two code paths for token lifecycle, not one.
- **Public API** — a plain `ApiKey` model (`Models/Auth/ApiKey.php`): a hashed, workspace-scoped, named-ability token, created/listed/revoked by workspace members from the Internal API, verified on Public requests by `EnsureApiKeyIsValid`. The old project never had a Public API, so there's no prior art to reuse here — this piece is new.

**Not to be confused with**: `ConnectOAuthCredentialAction`-style OAuth routes (`Connectors/`), which are for connecting a *user's own* third-party accounts (Gmail, Slack) as `ConnectorCredential`s — a completely different concern from platform auth.

## Package list

| Package | Purpose | Priority |
|---|---|---|
| `laravel/passport` | Internal API auth — Password Grant (access + refresh tokens) for the first-party React canvas, and Personal Access Tokens for social-login users | Essential, day one — already installed |
| *(custom, no package)* | `ApiKey` model — hashed, workspace-scoped, named-ability tokens for the Public API | Essential, day one |
| `laravel/socialite` | Google + GitHub login — Internal only | Essential, day one — already installed |
| `pragmarx/google2fa` | TOTP 2FA — Internal only | Phase 2 (old project uses `pragmarx/google2fa` directly, not the `-laravel` wrapper — no bundled routes/views to fight, since everything here is a JSON API) |
| *(Laravel core, no package)* | Rate limiting (named `throttle:` limiters), email verification (`MustVerifyEmail`), password reset (`Password` facade) | Essential, day one |

**Explicitly not used**:
- `laravel/sanctum` — considered first but not used, since Passport was already installed.
- `laravel/fortify` — its bundled controllers/views don't fit a decoupled JSON API with a separate frontend; a bespoke `AuthController` + `AuthService` gives full control over response shape.
- `spatie/laravel-permission` — redundant with the `WorkspaceContext` + `Enums/Workspaces/{Role,Permission}` workspace-scoped role system (see `docs/WORKSPACE_PLAN.md`).

## Feature scope, tiered

**Essential (blocks launch):**
1. Register / Login (Passport Password Grant, Internal)
2. Google / GitHub login (Socialite → Passport Personal Access Token, Internal)
3. Refresh access token (Passport refresh grant, Internal)
4. Logout + logout-all (token revocation, Internal)
5. Email verification (signed-route link, own controller — not Fortify's)
6. Forgot / reset password (reset link points at the frontend, `Password::reset()` also **revokes all Passport tokens** for that user as a security measure)
7. Change password (authenticated; optionally revoke all *other* sessions, keeping the current one active)
8. Workspace auto-creation on register + `WorkspaceContext` role resolution (see `docs/WORKSPACE_PLAN.md`)
9. Scoped `ApiKey`s (create/list/revoke, named abilities, Public)

**Important, but not day one:**
- 2FA (enable/confirm/disable/verify/recovery codes) — the old project's design (below) is proven and ready to port whenever this becomes a priority
- Session listing/revocation UI (`GET auth/sessions`, `DELETE auth/sessions/{id}`) — list/revoke individual Passport tokens by ID

**Defer indefinitely:**
- Security audit log — distinct from model-change activity logging; this is specifically login/auth-event history
- SSO/SAML/SCIM
- A real third-party OAuth **consent** flow — Passport is already in the project for Internal auth, so this is additive client/scope setup later, not a new package

## Folder structure

```
app/
  Http/
    Controllers/
      Api/
        Internal/
          V1/
            Auth/
              AuthController.php                 (register, login, refresh, logout, logoutAll,
                                                    forgotPassword, resetPassword, changePassword,
                                                    verifyEmail, resendVerification,
                                                    redirectToProvider, handleProviderCallback,
                                                    sessions, revokeSession)
              ApiKeyController.php               (index, store, destroy)
              TwoFactorController.php             (Phase 2 — deferred: enable, confirm, disable,
                                                    verify [completes login challenge],
                                                    recoveryCodes, regenerateRecoveryCodes)
            User/
              UserController.php                 (show, update, destroy — own profile, not "auth" per se)
    Requests/
      Api/
        Internal/
          V1/
            Auth/
              LoginRequest.php
              RegisterRequest.php
              ChangePasswordRequest.php
              StoreApiKeyRequest.php
              SocialCallbackRequest.php
              ConfirmTwoFactorRequest.php         (deferred)
              VerifyTwoFactorRequest.php          (deferred)
    Resources/
      Internal/
        SessionResource.php                     (id, ip/agent metadata if captured, created_at, is_current)
    Middleware/
      EnsureWorkspaceScope.php
      EnsureApiKeyIsValid.php               (guards Api/Public/V1/*)
      EnsureTwoFactorConfirmed.php           (deferred — only once 2FA ships)

  Models/
    User.php
    Credentials/
      OAuthConnection.php                     (provider, provider_id, avatar — links a User to a Google/GitHub identity)
    Auth/
      ApiKey.php

  Services/
    Auth/
      AuthService.php                          (all business logic behind AuthController's methods)
      TwoFactorAuthService.php                 (deferred — enable/confirm/disable/verifyCode/recoveryCodes)

  Enums/
    Auth/
      ApiKeyAbility.php                      (scopes: workflows:read, workflows:write, agents:invoke, runs:read, connectors:manage, *)

database/
  migrations/
    ..._create_oauth_connections_table.php     (user_id, provider, provider_id, avatar, unique(provider, provider_id))
    ..._add_two_factor_columns_to_users_table.php  (deferred — two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, all `encrypted`/`encrypted:array` cast)
    ..._create_api_keys_table.php

routes/
  api/
    internal/
      index.php                               (prefix('v1') — full path /api/v1/...)
      auth.php
      workspaces.php
    public/
      index.php                               (prefix('public/v1') — full path /api/public/v1/...)
      me.php                                  (placeholder until real Public resource routes exist — NOT api-key management, that's Internal's ApiKeyController)
```

**Revision note (versioning)**: Internal is versioned (`/api/v1/...`, controllers under `Api/Internal/V1/*`), unlike `STRUCTURE.md`'s original stance that Internal should stay unversioned since it deploys in lockstep with the frontend. That tradeoff was understood and the project owner chose to version it anyway for consistency with the Public surface's `V1` convention. Since both surfaces are versioned now, Public carries an explicit `public/` segment (`/api/public/v1/...`) to stay unambiguous against Internal's plain `/api/v1/...` — see `STRUCTURE.md`'s "Public vs. Internal API" section for the updated note.

**Why `Services/Auth/AuthService.php` instead of several `Actions/Auth/*Action.php` classes**: ported directly from the old project, which puts every auth code path (register, login, refresh, logout, logoutAll, forgotPassword, resetPassword, changePassword, social callback handling, 2FA challenge) behind one `AuthService` with one method per flow — controllers stay a thin `FormRequest → Service → response` shim. This differs from `STRUCTURE.md`'s general `Actions/`-per-use-case convention, which exists specifically so business logic is shared identically across Public/Internal/MCP — auth flows are Internal-only (Public auth is the separate `ApiKey` mechanism), so there's no cross-surface sharing need forcing the one-class-per-action split. Keep using `Actions/` for anything (like `Workflows/StartWorkflowRunAction`) that genuinely is called from more than one surface.

## Login flow detail (ported from the old project)

- `AuthService::login()` does **not** make a real HTTP call to `/oauth/token` — it dispatches an in-process kernel request (`app(Kernel::class)->handle(Request::create('/oauth/token', 'POST', $parameters))`), avoiding a network round trip and a live server dependency in tests. `refresh()` does the same with `grant_type=refresh_token`.
- If the user has 2FA enabled, `login()` does **not** issue a token at all — it caches `{user_id, password: Crypt::encryptString($password)}` under a random `2fa-challenge:{token}` key for 5 minutes and returns that challenge token to the client. `AuthService::completeTwoFactorChallenge($challengeToken, $code)` (called by `TwoFactorController::verify`) pulls the challenge (single-use — delete immediately), verifies the TOTP/recovery code, decrypts the password, and *then* performs the real password-grant exchange — so a 2FA-completed login still gets a full access+refresh token pair, not a lesser personal access token.
- Social login (`handleProviderCallback`) issues `$user->createToken('social-login-'.$provider)` — a personal access token, which is why it has no refresh token (see the asymmetry note below).
- `logout()` revokes the current `AccessToken` and its `RefreshToken`. `logoutAll()` revokes every token belonging to the user.

## Social login account linking (ported from the old project)

`Socialite::driver($provider)->stateless()->user()` (stateless — this is a JSON API, no server-side session to store OAuth state in), then in order:
1. Look up `OAuthConnection` by `(provider, provider_id)` → if found, use its `user`.
2. Else look up an existing `User` by matching email → link this new `OAuthConnection` to that account (auto-link by email match, no separate verification step — same behavior the old project shipped with).
3. Else create a new `User` with an unusable random password (`Str::password(32)`) and `email_verified_at` set immediately (social accounts are auto-verified, since the provider already verified the email).
4. Create the `OAuthConnection` row if one didn't exist, then issue the token.

`RedirectToSocialProviderController`-equivalent (`AuthController::redirectToProvider`) returns `{url}` as JSON (`Socialite::driver($provider)->stateless()->redirect()->getTargetUrl()`) rather than doing a server-side redirect — correct for a JSON API where the frontend does the actual browser redirect itself.

## Password policy & reset flow (ported from the old project)

- `Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols())` set once in `AppServiceProvider`.
- `VerifyEmail::createUrlUsing()` overridden to build a **signed route** (`signed` middleware, not `auth:api`) with a `sha1(getEmailForVerification())` hash param, 60-minute expiry — this is why `VerifyEmailController`'s route sits outside the authenticated group.
- `ResetPassword::createUrlUsing()` overridden to point at the **frontend** (`config('app.frontend_url').'/reset-password?token=...&email=...'`) instead of a Blade view, since the frontend is a separate SPA.
- `Password::reset()`'s closure force-fills the new password **and revokes all of that user's Passport tokens** — anyone logged in elsewhere gets kicked out on a password reset, which is the security-correct default.
- `ChangePasswordController`/`AuthService::changePassword()` takes an optional "revoke other sessions" flag so a deliberate password change doesn't have to nuke the session making the request.

## Rate limiting

Old project applies `middleware('throttle:auth')` to the entire `auth` route group, with `RateLimiter::for('auth', ...)` = 10 requests/minute by IP in `AppServiceProvider`. Reuse this as-is — cheap, effective brute-force mitigation on login/register/password-reset without needing per-route tuning.

## The token asymmetry (read before wiring up the frontend)

- **Password-grant tokens** (email/password login, and a completed 2FA challenge): access token (short-lived — old project uses 15 days, tune to your risk tolerance, 30–60 min is more typical for a Bearer-token SPA) + refresh token (long-lived, e.g. 30 days). The frontend must silently call `refresh` before the access token expires, or handle a 401 by refreshing and retrying.
- **Personal Access Tokens** (Google/GitHub login): long-lived (old project: 6 months), no refresh token, no expiry cycle. There is no `refresh_token` in the login response for these — don't build a single "always try refresh on 401" path that assumes one exists for every user.

## Verification

- Feature tests: `tests/Feature/Api/Internal/Auth/{Register,Login,RefreshToken,Logout,PasswordReset,EmailVerification,ApiKey,SocialLogin,Session}Test.php`
- `tests/Feature/Api/Public/ApiKeyAuthTest.php` — invalid key rejected, valid key resolves to its workspace, missing-ability key rejected for a gated action
- Once 2FA ships: `TwoFactorTest.php` covering enable→confirm→login-triggers-challenge→verify-completes-login→disable, and recovery-code consumption
- Manual check: register → confirm workspace auto-created (see `docs/WORKSPACE_PLAN.md`) → verify email (signed link) → login (access+refresh tokens) → refresh → change password (confirm other sessions revoked if requested) → logout. Separately: "Login with Google"/"Login with GitHub" end-to-end, including the same-email account-linking case, and confirm the resulting token has no `refresh_token`.
