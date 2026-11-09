---
title: API overview
description: The three API surfaces, base URLs, conventions and versioning.
sidebar_position: 1
---

# API overview

| Surface | Base path | Auth | Audience |
|---|---|---|---|
| **Public API** | `/api/public/v1` | Workspace API key | Your code, integrations, MCP |
| **Internal API** | `/api/v1` | OAuth2 bearer token (Passport) | The Agent1o1 web app |
| **Inbound webhooks** | `/api/hooks`, `/api/stripe/webhook`, `/api/slack/agent-actions` | URL token or provider signature | Triggers, Stripe, Slack |
| **Public pages** | `/api/v1/p/{token}` | Page access rules | Visitors |
| **Super admin** | configurable prefix | Separate admin tokens | Platform staff |

Both the public and internal APIs call the same business-logic classes, so behavior is identical.

:::warning
The Internal API is built for the first-party app and may change without notice. Build integrations on the Public API.
:::

## Conventions

- JSON in, JSON out. Send `Accept: application/json` and `Content-Type: application/json`.
- Resources use Laravel API Resources and wrap payloads in `data`. Lists are paginated with `links` and `meta`.
- Timestamps are ISO 8601 in UTC.
- IDs are opaque strings. Do not parse them.
- Errors follow one shape. See [Errors and limits](./errors-and-limits.md).

## Quick example

```bash
curl https://api.example.com/api/public/v1/workflows \
  -H "Authorization: Bearer $AGENT1O1_API_KEY" \
  -H "Accept: application/json"
```

## Rate limits

The Public API allows **60 requests per minute per API key**. Responses over the limit return `429` with `Retry-After`.

## Health check

`GET /up` returns `200` when the app is running.
