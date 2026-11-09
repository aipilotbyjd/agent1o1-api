---
title: Runs and logs
description: Starting runs, reading node-level logs and costs, cancelling and retrying.
sidebar_position: 5
---

# Runs and logs

## Starting a run

| Source | How |
|---|---|
| App | **Run** button in the editor |
| API | `POST /api/public/v1/workflows/{workflow}/runs` |
| Trigger | Webhook, schedule, polling or app event |
| Agent | The agent calls the workflow as a tool |
| Parent workflow | Sub-workflow node |
| Public page | A form submission on a [public page](../09-content/pages.md) |

## Run statuses

| Status | Meaning |
|---|---|
| `pending` | Created, waiting for a worker |
| `running` | At least one node is executing |
| `awaiting_approval` | Paused on a Human Approval node |
| `awaiting_callback` | Paused on a Wait node |
| `completed` | All required nodes finished |
| `failed` | A node failed with no recovery path |
| `cancelled` | Stopped by a user or the API |

Node runs use their own statuses (pending, running, completed, failed, skipped, waiting).

## Reading a run

For each run you can see:

- the trigger source and the input payload,
- every node run: input, output, duration, error, retry count,
- **cost** (`GET /runs/{run}/cost`): credits used per node and in total,
- artifacts produced.

## Cancel and retry

- **Cancel** stops a run at the next safe point.
- **Retry** creates a new attempt from the failed state without redoing completed nodes.

## Realtime updates

The web app subscribes to workspace channels over WebSockets (Reverb), so node statuses update live. See [Realtime](../12-api/realtime.md).

## Retention

Trigger events and run data are pruned on a schedule. Check your plan and the self-hosting `config/triggers.php` retention settings.
