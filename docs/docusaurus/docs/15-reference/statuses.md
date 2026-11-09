---
title: Statuses and enums
description: Values returned by the API for runs, triggers, agents, billing and more.
sidebar_position: 1
---

# Statuses and enums

## Runs

`pending`, `running`, `awaiting_approval`, `awaiting_callback`, `completed`, `failed`, `cancelled`.

## Port types

`text`, `list_text`, `list_list_text`, `file`, `boolean`, `number`.

## Triggers

| Enum | Values |
|---|---|
| Type | `webhook`, `schedule`, `polling`, `manual` |
| Target | workflow, agent |
| Event status | received, dispatched, ignored, failed |

## Workflow builder

| Enum | Values |
|---|---|
| Message mode | `build`, `ask` |
| Message status | `pending`, `processing`, `completed`, `failed` |
| Session status | `active`, `promoted`, `archived` |

## Interface field types

`string`, `text`, `number`, `boolean`, `select`, `json`.

## Page access

`workspace`, `link`, `password`.

## Agents

| Enum | Values |
|---|---|
| Autonomy mode | `read_only`, `ask`, `plan`, `smart`, `autopilot` |
| Session, plan, action | shown in the app and API responses |

## Workspace roles

`owner`, `admin`, `editor`, `member`, `viewer`.

## API key abilities

`*`, `workflows:read`, `workflows:write`, `agents:invoke`, `runs:read`, `connectors:manage`.

## Plan limit keys

`workflows`, `agents`, `members`, `pages`, `active_triggers`.

## Plan feature keys

`credit_packs`, `credit_overage`, `git_sync`, `workflow_approvals`, `custom_nodes`, `priority_support`, `public_pages`, `remove_branding`, `smart_inbox`, `assistant_sandbox`, `assistant_sms`.
