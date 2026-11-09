---
title: Internal API map
description: A map of the first-party API used by the web app, by resource.
sidebar_position: 4
---

# Internal API map

Base: `/api/v1`. Auth: bearer token. Most routes live under `/workspaces/{workspace}/...` and are checked with workspace membership, role and plan limits.

This page is a **map**, not a contract. It can change with the app.

| Area | Prefix | Highlights |
|---|---|---|
| Auth | `/auth` | register, login, refresh, 2FA, social, sessions, events |
| User | `/user` | profile, avatar, email change, switch workspace |
| Workspaces | `/workspaces` | CRUD, members, invitations, notification channels, preferences, audit logs |
| API keys | `/workspaces/{w}/api-keys` | list, create, revoke |
| Workflows | `/workspaces/{w}/workflows` | CRUD, trash and restore, duplicate, versions, publish requests, interface, tags, favorite, pin node |
| Builder | `/workspaces/{w}/workflow-builder-sessions` | AI builder sessions, messages, assist |
| Pages | `/workspaces/{w}/pages`, `/page-sections` | page CRUD, sections, comments |
| Folders, tags | `/folders`, `/tags` | organize, move workflows and agents |
| Nodes | `/workspaces/{w}/nodes` | catalog, options, custom nodes |
| Runs | `/workspaces/{w}/runs` | list, detail, cost, cancel, retry, node runs, approval decisions |
| Triggers | `/workspaces/{w}/triggers` | CRUD, run, sample, replay, rotate token, events |
| Agents | `/workspaces/{w}/agents` | CRUD, versions, sessions, messages, turns, actions, plans, tool bindings, workflows, skills, subagents, knowledge, evals, memories, reflections |
| Skills | `/workspaces/{w}/skills` | CRUD, references, scripts, sources |
| Knowledge | `/workspaces/{w}/knowledge-base` | search, sources, documents, collections |
| Assistant | `/workspaces/{w}/assistant` | sessions, messages, briefings, inbox, triggers, memory, channels |
| Connections | `/workspaces/{w}/connector-credentials`, `/connectors` | list, OAuth, test, usage |
| Secrets | `/workspaces/{w}/secrets` | CRUD |
| AI keys | `/ai-provider-credentials` | add, check, connect |
| Models | `/workspaces/{w}/models` | catalog and settings |
| Artifacts | `/workspaces/{w}/artifacts` | upload, preview, download, access, shares |
| Templates | `/workflow-templates`, `/agent-templates`, `/page-templates`, `/template-collections` | browse and use |
| Library | `/workspaces/{w}/library` | unified search |
| Billing | `/workspaces/{w}/billing` | plans, subscription, packs, credits, overage, invoices, portal |
| Referrals | `/referrals` | code, program, stats, rewards |
| Notifications | `/notifications` | list, read |
| Announcements | `/announcements` | list, dismiss |
| Onboarding | `/onboarding` | steps and answers |
| Dashboard | `/workspaces/{w}/dashboard` | summary metrics |

Use `php artisan route:list --path=api/v1` for the complete, current list.
