---
title: Autonomy and approvals
description: Control what an agent may do alone, ask for approval, plan first, and set workspace policy.
sidebar_position: 3
---

# Autonomy and approvals

## Autonomy modes

| Mode | Behavior |
|---|---|
| `read_only` | Only tools that read. Anything that writes is blocked. |
| `ask` | Every write or external action needs approval. |
| `plan` | The agent writes a plan first. You approve or reject it, then it executes. |
| `smart` | Low-risk actions run alone. Risky ones ask. |
| `autopilot` | Runs everything allowed by policy without asking. |

## Action risk and verdicts

Each tool call is classified by **risk** and **effect**. The gate returns a **verdict**: allow, ask or deny. Pending actions are listed on the agent's **Actions** page and per session.

## Approving actions

- In the app: the session shows pending actions. Approve or reject, in bulk with decisions.
- By API: `POST /agents/{agent}/sessions/{session}/actions/decisions`.
- By Slack: buttons in the notification post to `/api/slack/agent-actions`.
- By signed link: an email or message link carries a signed decision.

Pending actions expire automatically (`agents:expire-actions` runs every minute).

## Plans

In `plan` mode, a plan is created with steps. `POST .../plans/{plan}/approve` or `/reject`.

## Trust suggestions

After you approve the same kind of action repeatedly, the agent suggests **trust rules** to skip future prompts. Review and apply them at `/agents/{agent}/trust-suggestions`.

## Workspace agent policy

Admins set a workspace-wide policy (**Settings → Agent policy**) that caps the most permissive mode agents may use. Policy always wins over an agent's own setting. Permission: `agent.policy.manage`.

## Who can approve

Permission `agent.approve`. Chatting needs `agent.chat`.
