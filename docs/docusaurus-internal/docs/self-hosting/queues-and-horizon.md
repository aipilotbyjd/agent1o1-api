---
title: Queues, scheduler and Horizon
description: Queue names, worker supervisors, scheduled commands and monitoring.
sidebar_position: 3
---

# Queues, scheduler and Horizon

All execution is asynchronous. Redis holds the queues, and **Horizon** supervises workers.

## Queues

| Queue | Used for |
|---|---|
| `workflows-dispatch` | Resolving the graph and queueing nodes |
| `workflows-execute` | Running nodes |
| `ai-agent` | Agent turns (low concurrency to respect provider limits) |
| `ai-assistant` | Assistant turns |
| `ai-subagent` | Sub-agent tasks |
| `workflow-builder` | AI workflow builder |
| `triggers-poll` | Polling checks |
| `triggers-event` | Trigger event dispatch |
| `billing-webhook` | Stripe webhook processing (highest priority) |
| `system-maintenance` | Housekeeping |
| `system-notification` | Notifications |
| `system-broadcast` | Broadcast emails |
| `system-long-running` | Long jobs |

Each queue has its own Horizon supervisor so unrelated workloads cannot starve each other. Tune `maxProcesses` in `config/horizon.php`.

## Processes to run

| Process | Command | Dockerfile |
|---|---|---|
| Web | PHP-FPM or similar | `Dockerfile.web` |
| Horizon | `php artisan horizon` | `Dockerfile.horizon` |
| Scheduler | `php artisan schedule:work` | `Dockerfile.scheduler` |
| Reverb | `php artisan reverb:start` | `Dockerfile.reverb` |

## Scheduled commands

The scheduler runs on one server at a time without overlap. Key jobs:

| Area | Commands |
|---|---|
| Triggers | `triggers:run-due` (1m), `triggers:retry-stuck` (5m), `triggers:flush-batches` (1m), `triggers:renew-watches` (10m), `triggers:collect-watches` (hourly), `triggers:prune-events` (daily) |
| Agents | `agents:expire-actions` (1m), `reflections:run-due` (15m) |
| Assistant | `assistant:expire-actions`, `run-due-briefings`, `run-due-triggers` (1m); `check-inboxes` (2m); `check-follow-ups` (15m); `sync-meetings` (10m) |
| Connections | `connectors:refresh-expiring` (5m), `connectors:check-health` (hourly), `ai-credentials:check` (hourly), `ai-credentials:refresh-tokens` (5m) |
| Knowledge and skills | `knowledge:sync-sources`, `skills:sync-sources` (15m) |
| System | `system:heartbeat` (1m), `model-catalog:retire-due` (daily), `broadcasts:dispatch-due` (1m) |
| Super admin | `system:health-snapshot`, `alerts:evaluate`, `alerts:digest`, `alerts:prune`, `revenue:snapshot`, `super-admin:audit-verify` |

## Monitoring

- **Horizon** dashboard: queue throughput, wait times, failed jobs. Protected by a gate.
- **Pulse**: application performance dashboard.
- **Health**: `/up`, and the super admin health snapshot including the `setup_data` check.
