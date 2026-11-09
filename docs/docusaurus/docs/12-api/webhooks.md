---
title: Webhooks
description: URLs you can call to start workflows, resume paused runs and reach your assistant.
sidebar_position: 5
---

# Webhooks

These URLs are public and authenticate themselves with a token in the URL.

| Endpoint | Purpose |
|---|---|
| `POST/GET /api/hooks/{token}` | Start a workflow or agent from a [webhook trigger](../04-triggers/webhook.md) |
| `POST /api/hooks/wait/{token}` | Resume a run paused on a [Wait node](../02-workflows/flow-control.md#delay-and-wait) |
| `POST /api/hooks/assistant/{token}` | Send a message to your personal assistant |

Treat the token like a password. You can rotate a trigger's token at any time, and the old URL stops working immediately.

## Delivery guarantees

Events are stored before processing and retried if a run is delayed. Return `2xx` quickly from your own systems, and do not rely on ordering.

## Rate limits

Webhook URLs are rate limited. Over the limit returns `429`. Retry with backoff.
