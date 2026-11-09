---
title: Create an agent
description: Build an AI agent with instructions, abilities and a model, and chat with it.
sidebar_position: 1
---

# Create an agent

An agent is an AI that works toward a goal. You give it instructions and abilities, and it decides which to use. Unlike a playbook, its steps aren't fixed.

**Examples:** a support agent that answers questions from your help docs, a research agent that searches the web and writes a summary, a scheduling agent that books meetings.

## Create one

1. Open **Agents** in the sidebar and choose to add an agent, or start from a [Blueprint](../07-pages-and-files/blueprints.md).
2. Give it a **name** and **description**.
3. Write its **instructions**: who it is, what it should do, how it should sound, and what it must never do. Use the improve option to have AI tighten a rough draft.
4. Choose a **model** and, if you want, a **temperature** (lower is more predictable, higher is more creative).
5. Give it [abilities](./abilities.md), [knowledge](./knowledge.md) and [skills](./skills.md).
6. Choose how much it may do alone. See [Autonomy and approvals](./autonomy.md).

## Chat with it

Use the chat on the agent's page to try it. Each conversation is saved, so you can come back to it, rename it or delete it. Fix its instructions when it gets something wrong, and try again.

## Where agents can be used

- In chat, in the app
- As a step in a [playbook](../03-playbooks/steps.md)
- Started by a [trigger](../03-playbooks/triggers.md)
- From your own code through the [API](../09-developers/api-overview.md)

## Versions

Every change to an agent is saved. Open the history to compare and restore an earlier version.

## Insights

The agent's **Insights** page shows how it is doing and suggests improvements. See [Insights and testing](./insights.md).

## Limits

How many agents you can have depends on your [plan](../08-billing/plans.md).
