---
title: Personal assistant overview
description: A personal AI assistant, separate from shared agents, with chat, briefings and an inbox.
sidebar_position: 1
---

# Personal assistant overview

The **Assistant** is a personal AI for one user. It is separate from workspace [agents](../05-agents/overview.md): it has its own sessions, memory, tools, triggers and billing rules. The display name, emoji, color and tagline are configurable (`ASSISTANT_*` settings).

## What it does

| Feature | Page |
|---|---|
| Chat in the app | This page |
| **Daily briefing** and **meeting prep** | [Briefings](./briefings.md) |
| **Smart Inbox** (classify and draft replies) | [Smart Inbox](./smart-inbox.md) |
| Reach it by Slack, email, SMS | [Channels](./channels.md) |
| Recurring tasks and watchers | [Assistant triggers](./triggers.md) |
| Sandbox computer (Pro) | Feature `assistant_sandbox` |

## Core ideas

- **Turns**: each message starts a turn that can call tools, shorten long context, and hold follow-up input.
- **Tool gate and rules**: each tool has an effect (read, write, external). Rules decide allow, ask or block per tool. Approvals appear in chat and can be answered from Slack.
- **Memory and style**: the assistant remembers facts and learns your writing style from feedback and edits.
- **Feedback**: thumbs up or down with notes improves later answers.
- **Situations**: tracked ongoing matters with steps, surfaced in briefings.
- **Follow-ups**: it notices things you are waiting on and reminds you.

## Permissions

Only the owner uses their assistant (`assistant.use`). Workspace admins cannot read another member's assistant data.

## Plan features

`smart_inbox`, `assistant_sandbox` and `assistant_sms` are Pro-level features. Chat and briefings follow the workspace credit rules.
