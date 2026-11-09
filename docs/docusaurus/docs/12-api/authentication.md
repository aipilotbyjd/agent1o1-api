---
title: Authentication
description: API keys for the Public API and OAuth2 tokens for the Internal API.
sidebar_position: 2
---

# Authentication

## Public API: API keys

Create keys in **Settings → API keys** (permission `api-key.manage`) or with `POST /workspaces/{workspace}/api-keys`.

- A key belongs to one workspace and carries **abilities**.
- The full key is shown **once**. Only a hash is stored.
- Revoke a key any time. Use one key per integration.

Send it as a bearer token:

```
Authorization: Bearer YOUR_API_KEY
```

### Abilities

| Ability | Allows |
|---|---|
| `workflows:read` | List and read workflows, graphs and interfaces |
| `workflows:write` | Create, update, delete and publish workflows; start, cancel and retry runs |
| `runs:read` | Read runs and node runs |
| `agents:invoke` | List agents, create sessions, send messages, decide on actions |
| `connectors:manage` | List connectors, create and delete connection records |
| `*` | Everything |

Missing ability returns `403`.

## Internal API: OAuth2 (Passport)

The web app exchanges an email and password for an access token and refresh token using the **password grant**. Social login (Google, GitHub) uses a one-time exchange (`/auth/social/exchange`) that returns a long-lived personal access token.

| Endpoint | Purpose |
|---|---|
| `POST /auth/register` | Create account and workspace |
| `POST /auth/login` | Email and password. Returns tokens, or a 2FA challenge |
| `POST /auth/2fa/verify` | Complete a 2FA challenge |
| `POST /auth/refresh` | Refresh the access token |
| `POST /auth/logout`, `/logout-all` | Revoke the current or all sessions |
| `POST /auth/forgot-password`, `/reset-password` | Password reset (revokes all tokens) |
| `POST /auth/change-password` | Change password |
| `GET /auth/social/{provider}/redirect` | Start Google or GitHub login |
| `GET /auth/sessions`, `DELETE /auth/sessions/{id}` | List and revoke sessions |
| `GET /auth/events` | Your sign-in history |
| `POST /auth/2fa/enable`, `confirm`, `disable` | Manage 2FA |

:::note Token asymmetry
Password-grant tokens are short-lived and refreshable. Social-login personal access tokens are long-lived and are not refreshed. Frontends must handle both.
:::

Auth routes are throttled to 10 requests per minute per IP.

## Webhooks

Webhook URLs authenticate with the token in the URL. Stripe, Slack, Telegram, Discord, Twilio and similar providers are verified with their signature headers. See [Webhooks](./webhooks.md).
