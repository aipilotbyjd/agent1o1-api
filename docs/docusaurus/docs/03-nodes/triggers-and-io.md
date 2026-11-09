---
title: Triggers and I/O nodes
description: Trigger nodes that start workflows and Input/Output nodes that define the workflow interface.
sidebar_position: 4
---

# Triggers and I/O nodes

## Trigger nodes

| Node | Starts a run when |
|---|---|
| **Webhook trigger** | An HTTP request arrives at the workflow's URL |
| **Schedule trigger** | A cron schedule is due |
| **Polling trigger** | A periodic check of an app finds new items |
| **App triggers** | An app pushes an event (for example a new Slack message or Stripe payment) |
| **Manual trigger** | Retired. Kept only so older workflows still load. |

Triggers share delivery controls: **activate switch**, **skip if running**, **failure breaker**, **runs per minute**, **grouping/batching** and **concurrency**. Full details: [Triggers](../04-triggers/overview.md).

## Workflow Input

Defines a field callers provide. Set name, label, type (`string`, `text`, `number`, `boolean`, `select`, `json`), default and description.

## Workflow Output

Defines a value returned to the caller. A run's final output is the set of Workflow Output values.

Together they form the **workflow interface**, shown in the Run form, API, agent tools and [public pages](../09-content/pages.md).
