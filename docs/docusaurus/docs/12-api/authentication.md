---
title: Authentication
description: How to create and use API keys.
sidebar_position: 2
---

# Authentication

## API keys

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

## Webhooks

Webhook URLs authenticate with the token in the URL. See [Webhooks](./webhooks.md).
