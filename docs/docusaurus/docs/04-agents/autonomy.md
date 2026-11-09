---
title: Autonomy and approvals
description: Decide how much an agent can do on its own, and set workspace-wide rules.
sidebar_position: 3
---

# Autonomy and approvals

Autonomy is how much an agent may do without asking you first.

## Modes

| Mode | What the agent does |
|---|---|
| **Read only** | Looks things up. Never changes anything. |
| **Ask** | Asks you before every action that changes something |
| **Plan** | Writes a plan first. You approve it, then it carries it out |
| **Smart** | Does low-risk things alone and asks about risky ones |
| **Autopilot** | Does everything its abilities allow, without asking |

Start with **Ask** or **Plan**, and move up as you gain trust.

## Approving requests

When an agent needs permission, the request appears in the chat and on the **Approvals** page. You can approve or reject, and approve several at once. If Slack approvals are on, you can decide from Slack. Requests expire if nobody answers.

## Suggested trust rules

If you keep approving the same kind of action, the agent suggests a rule to stop asking about it. Review each suggestion before you accept it.

## Workspace agent policy

Admins can set rules for every agent in **Settings → Agent policy**:

- The **highest autonomy mode** any agent may use
- How long **approval requests** stay open
- Whether people can **decide from Slack**
- Whether **Autopilot may delete** without asking (off by default, so deleting always asks)
- Named rules that apply to specific tools, for example "always ask before any email tool"

The workspace policy always wins over an individual agent's setting.
