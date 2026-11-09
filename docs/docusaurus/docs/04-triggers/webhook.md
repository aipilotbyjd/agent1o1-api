---
title: Webhook triggers
description: Start a workflow from any system with an HTTP request.
sidebar_position: 2
---

# Webhook triggers

Each webhook trigger has a unique URL. The token inside the URL is the credential.

```
POST https://api.example.com/api/hooks/{token}
GET  https://api.example.com/api/hooks/{token}
```

```bash
curl -X POST https://api.example.com/api/hooks/TOKEN \
  -H "Content-Type: application/json" \
  -d '{"customer": "acme", "amount": 42}'
```

The request body, query string and headers become the trigger's output and are available to the nodes below it.

## Authentication modes

| Mode | Behavior |
|---|---|
| URL token only | Anyone with the URL can call it |
| Shared secret / signature | The caller signs the body; unsigned or wrongly signed requests are rejected |

Rotate the token at any time with `POST /triggers/{trigger}/rotate-token`. The old URL stops working immediately.

## Rate limiting

Hook endpoints are rate limited (`trigger-hooks` limiter). Callers get `429` when over the limit.

## Responses

The endpoint acknowledges quickly after the event is stored, then the workflow runs asynchronously. Fetch results using the run id or add a Wait callback.

## Testing

Use **Sample** in the trigger panel to see a sample payload, and **Run** to fire a test event. Failed events can be **replayed** from the events list.

## Wait callbacks

A different URL, `POST /api/hooks/wait/{token}`, resumes a run paused on a [Wait node](../02-workflows/flow-control.md#delay-and-wait).
