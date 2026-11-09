---
title: Assistant triggers
description: Recurring tasks and event-driven prompts for the assistant.
sidebar_position: 5
---

# Assistant triggers

Assistant triggers are the assistant's own, separate from workflow triggers. Each one stores a prompt and a condition.

| Type | Fires |
|---|---|
| Schedule | On a cron or fixed time |
| Webhook | When its URL is called |
| Mail or calendar event | When a matching email or event appears |

Due triggers are checked every minute (`assistant:run-due-triggers`). A fired trigger starts a turn in a new session. Statuses: active, paused, failed.

Each trigger can be set to ask for approval before any action, regardless of the assistant's tool rules.
