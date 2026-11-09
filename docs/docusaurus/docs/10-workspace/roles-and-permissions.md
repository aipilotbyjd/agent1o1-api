---
title: Roles and permissions
description: The five workspace roles and what each permission allows.
sidebar_position: 2
---

# Roles and permissions

## Roles

| Role | Typical use |
|---|---|
| **Owner** | Full control, including billing and deleting the workspace |
| **Admin** | Manage members, connections, settings, policy |
| **Editor** | Build and publish workflows, agents and pages |
| **Member** | Run workflows and chat with agents |
| **Viewer** | Read only |

## Permission groups

| Area | Permissions |
|---|---|
| Workspace | `workspace.view`, `workspace.update`, `workspace.delete` |
| Members | `member.view`, `member.invite`, `member.update-role`, `member.remove`, `invitation.view` |
| Workflows | `workflow.view`, `.manage`, `.publish`, `.version`, `.trigger`, `.builder.use`, `.approve-publish` |
| Pages | `page.view`, `page.manage` |
| Nodes | `node.view`, `node.manage` |
| Agents | `agent.view`, `.manage`, `.chat`, `.skill.manage`, `.approve`, `.policy.manage` |
| Assistant | `assistant.use` |
| Connections | `connector.view`, `connector.manage` |
| AI credentials | `ai-credential.view`, `.use-personal`, `.manage` |
| Secrets | `secret.view`, `secret.manage` |
| Templates | `template.view`, `template.manage` |
| Artifacts | `artifact.view`, `artifact.manage` |
| Runs and triggers | `run.view`, `run.trigger`, `trigger.view`, `trigger.manage` |
| API keys | `api-key.view`, `api-key.manage` |
| Billing | `billing.view`, `billing.manage` |
| Notifications | `notification-channel.view`, `notification-channel.manage` |
| Audit | `audit-log.view` |

## API keys vs roles

API keys do not have a role. They carry **abilities** (`workflows:read`, `workflows:write`, `agents:invoke`, `runs:read`, `connectors:manage`, `*`). See [Authentication](../12-api/authentication.md).
