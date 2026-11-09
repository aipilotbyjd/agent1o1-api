---
title: Triggers overview
description: How workflows and agents start automatically, and how events are delivered reliably.
sidebar_position: 1
---

# Triggers overview

A trigger starts a run when something happens. Four types exist:

| Type | Starts when |
|---|---|
| [Webhook](./webhook.md) | An HTTP request hits the trigger URL |
| [Schedule](./schedule.md) | A cron expression is due |
| [Polling](./polling-and-app-triggers.md) | A recurring check finds new data in an app |
| [App event](./polling-and-app-triggers.md) | An app pushes an event to us |

Manual is retired but still loads in older workflows.

A trigger targets a workflow or an agent.

## The delivery pipeline

Every event goes through a durable inbox before anything runs:

```
Event arrives ─► verify (token/signature) ─► store TriggerEvent ─► conditions ─► dispatch run
                                                    │
                                          retry-stuck recovery ─► replay on demand
```

1. **Verify**: webhook token in the URL, or the provider's signature.
2. **Store**: the event is saved first, so the event is never lost if a worker is down.
3. **Conditions**: [Only run when](./conditions.md) rules decide `dispatched` or `ignored`.
4. **Dispatch**: starts the run.
5. **Recover**: stuck events are retried; failed ones can be **replayed**.

Event statuses are visible per trigger: `GET /workspaces/{workspace}/triggers/{trigger}/events`.

## Shared controls

| Control | What it does |
|---|---|
| Activate switch | Turn a trigger on or off without deleting it |
| Skip if running | Ignore new events while a previous run is still going |
| Failure breaker | Auto-disable after repeated failures (reason is recorded) |
| Runs per minute | Rate limit run starts |
| Grouping / batching | Collect events and flush them as one run |
| Concurrency | Cap parallel runs from this trigger |

## Plan limits

Free allows 2 active triggers with a 15-minute minimum polling interval. Starter allows 25 at 5 minutes. Pro and Business are unlimited at 1 minute.

## Managing triggers

- App: **Settings → Triggers** lists every trigger in the workspace.
- API: `GET/POST /workspaces/{workspace}/triggers`, `PATCH/DELETE /triggers/{trigger}`, `POST /triggers/{trigger}/run` (test run), `GET /triggers/{trigger}/sample` (sample payload), `POST /triggers/{trigger}/events/{event}/replay`, `POST /triggers/{trigger}/rotate-token`.
- Presets: `GET /catalog/trigger-presets` lists ready-made triggers per app.
