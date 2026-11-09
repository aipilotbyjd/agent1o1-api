---
id: intro
title: Welcome to Agent1o1
description: Agent1o1 is a platform for building AI-powered workflows and conversational agents without writing glue code.
slug: /
sidebar_position: 0
---

# Welcome to Agent1o1

Agent1o1 is a platform for building **AI-powered automations**. You connect your apps, drag nodes onto a canvas, and let workflows and agents do the repetitive work.

It combines four things in one workspace:

| Building block | What it is | Use it when |
|---|---|---|
| **Workflows** | A graph of typed nodes (triggers, AI, data, integrations) that runs the same way every time | The steps are known and you want repeatable, auditable results |
| **Agents** | Conversational AI that decides which tools to call (connectors, workflows, skills) | The task is open-ended or needs judgment |
| **Assistant** | A personal AI assistant with briefings, a Smart Inbox, and chat channels (web, Slack, email, SMS) | You want help with your own day, not a shared automation |
| **API** | A public REST API plus webhooks | You want to run workflows or agents from your own code |

## Where to start

- New here? Read [Core concepts](./01-getting-started/core-concepts.md), then follow the [Quickstart](./01-getting-started/quickstart.md).
- Building automations? Go to [Workflows](./02-workflows/overview.md) and the [Node catalog](./03-nodes/overview.md).
- Building an AI agent? Go to [Agents](./05-agents/overview.md).
- Integrating from code? Go to the [API overview](./12-api/overview.md).

## How the platform fits together

```
            ┌────────────────────────────────────────────┐
 Triggers → │  Workflow engine (node by node)             │ → Runs, Artifacts
 (webhook,  │   AI nodes · Data nodes · Flow logic ·     │
 schedule,  │   60+ app integrations                      │
 app events)└──────────────▲─────────────────────────────┘
                            │ call as tools
            ┌───────────────┴─────────────┐
            │  Agents                     │ ← Sessions, Skills, Knowledge, Memory
            └──────────────▲──────────────┘
                           │ same business logic
        Web app                    ·  Public API (/api/public/v1)
                                      used by your code
```

Everything is scoped to a **workspace**. Usage is metered in **credits**, and billed through Stripe.

:::tip Documentation conventions
Code samples use `https://api.example.com` as the API host. Replace it with your own deployment's URL.
:::
