---
title: API overview
description: Run playbooks and agents from your own code using an API key.
sidebar_position: 1
---

# API overview

The Agent1o1 API lets your own code run playbooks, read results and talk to agents. You don't need it to use the app.

## Create an API key

1. Open **Settings → API keys** and choose **Create your first key** (or add another).
2. Enter a **name** such as "Production integration" and, if you like, an **expiry date**.
3. Choose what the key may do (see below).
4. **Copy the key now**. It's shown only once. If you lose it, create a new one.

Revoke a key any time from the same page. Use one key per integration, so you can revoke one without affecting others.

## What a key can do

| Permission | Lets the key |
|---|---|
| `workflows:read` | List and read playbooks |
| `workflows:write` | Create, change and publish playbooks, start, cancel and retry runs |
| `runs:read` | Read runs and their steps |
| `agents:invoke` | Use agents: start conversations and send messages |
| `connectors:manage` | Manage app connections |
| `*` | Everything |

Give each key only what it needs. Playbooks are called "workflows" in the API.

## Make a request

```bash
curl https://YOUR-API-HOST/api/public/v1/workflows \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
```

Use the API address given to you by Agent1o1 in place of `YOUR-API-HOST`.

## Conventions

- Send and receive JSON.
- Lists are paginated.
- Times are in UTC.
- Limit: 60 requests per minute for each key.

## Next

- [Run a playbook from code](./run-a-playbook.md)
- [Talk to an agent from code](./talk-to-an-agent.md)
- [Webhooks](./webhooks.md)
- [Errors and limits](./errors.md)
