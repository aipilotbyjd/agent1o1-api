---
title: What is a playbook
description: A playbook is a series of connected steps that runs the same way every time.
sidebar_position: 1
---

# What is a playbook

A playbook is a series of steps you connect on a canvas. Each step does one thing, and passes its result to the next.

**Example:** a new lead fills in your form → AI scores the lead → if the score is high, add them to your CRM and message your sales channel on Slack.

## When to use a playbook

Use a playbook when you know the steps and want them done the same way every time. If the task is open-ended and needs judgement, use an [agent](../04-agents/overview.md). You can also combine them: give an agent a playbook as one of its abilities.

## How it works

1. **Something starts it.** You press **Run**, a schedule is due, or an event happens in an app. See [Starting playbooks automatically](./triggers.md).
2. **Steps run in order.** Each step gets the results of the steps before it.
3. **The run is recorded** in the [Trail](./runs-and-trail.md), step by step.

## The Playbooks page

Open **Playbooks** in the sidebar to see all your playbooks. You can search, sort, switch between grid and list view, filter by tag, group into folders, and star favourites. See [Organise your playbooks](./organising.md).

## Limits

How many playbooks you can have, how many run at once, and how many credits one run may use depend on your [plan](../08-billing/plans.md).

## In this section

- [Build a playbook](./building-playbooks.md)
- [Steps you can use](./steps.md)
- [Control the flow: filters, loops, waits and approvals](./flow-control.md)
- [Starting playbooks automatically](./triggers.md)
- [Runs and the Trail](./runs-and-trail.md)
- [Publish and versions](./publishing.md)
- [Organise your playbooks](./organising.md)
