---
title: API overview
description: Base URLs, conventions and rate limits for the Public API.
sidebar_position: 1
---

# API overview

| Surface | Base path | Auth | Audience |
|---|---|---|---|
| **Public API** | `/api/public/v1` | Workspace API key | Your code and integrations |
| **Inbound webhooks** | `/api/hooks` | URL token | Systems that start workflows |
| **Public pages** | `/api/v1/p/{token}` | Page access rules | Visitors |

## Conventions

- JSON in, JSON out. Send `Accept: application/json` and `Content-Type: application/json`.
- Responses wrap payloads in `data`. Lists are paginated with `links` and `meta`.
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
