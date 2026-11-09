---
title: Realtime updates
description: WebSocket updates over Laravel Reverb for runs, agents and notifications.
sidebar_position: 7
---

# Realtime updates

Agent1o1 pushes live events over **Laravel Reverb** (WebSockets, Pusher protocol), using Laravel Echo in the web app.

## What is broadcast

- Run and node-run status changes
- Agent session messages, tool steps and pending approvals
- Assistant messages and tool activity
- Notifications and announcements
- Workflow page and builder events

## Channels

Channels are private and scoped to a workspace. Authorization happens at the Passport-protected broadcast auth endpoint, and `WorkspaceChannelGate` verifies membership. Broadcasts use a reliable delivery wrapper so events survive short connection drops.

## Connecting from the frontend

Environment variables in the web app:

| Variable | Purpose |
|---|---|
| `VITE_REVERB_APP_KEY` | App key |
| `VITE_REVERB_HOST` | Reverb host |
| `VITE_REVERB_PORT` | Reverb port |
| `VITE_REVERB_SCHEME` | `http` or `https` |

If realtime is down the app falls back to refetching, so nothing breaks but updates arrive late.

## Public API users

The Public API has no WebSocket access. Poll `GET /runs/{run}` or use a [Wait callback](../02-workflows/flow-control.md#delay-and-wait).
