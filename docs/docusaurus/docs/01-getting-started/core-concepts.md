---
title: Core concepts
description: The vocabulary used across Agent1o1 — workspaces, workflows, nodes, runs, agents, connectors, credits.
sidebar_position: 1
---

# Core concepts

## Workspace

A **workspace** is the tenant boundary. Workflows, agents, connections, secrets, API keys, members and billing all belong to one workspace. A user can belong to several workspaces and switch between them. A personal workspace is created when you sign up.

See [Workspaces](../10-workspace/workspaces.md) and [Roles and permissions](../10-workspace/roles-and-permissions.md).

## Workflow

A **workflow** is a directed graph of nodes joined by edges. It is saved as a draft, then **published** as an immutable **version**. Runs always execute a published version, so editing never breaks something already running.

## Node

A **node** is one step. Every node has typed inputs, configuration, and typed outputs. Nodes fall into categories:

- **Triggers and events**: start a workflow.
- **AI automation**: ask, extract, classify, summarize, translate, image, speech and more.
- **Data transform**: parse, format, scrape, call an API, store data.
- **Flow logic**: filter, route, loop, wait, approve, join, call a sub-workflow.
- **Inputs and outputs**: the workflow's public interface.
- **Integrations**: 60+ apps such as Slack, Gmail, Google Sheets, Notion, HubSpot, Stripe.

## Edge and port types

An **edge** connects an output port to an input port. Ports are typed (`text`, `list of text`, `list of list of text`, `file`, `boolean`, `number`). The editor and the server both reject connections that don't type-check, before the workflow can run.

## Run and node run

A **run** is one execution of a workflow. Each node inside it produces a **node run** with its own status, input, output, error and credit cost. Run history is your audit log.

Run statuses: `pending`, `running`, `awaiting_approval`, `awaiting_callback`, `completed`, `failed`, `cancelled`.

## Trigger

A **trigger** starts a workflow (or agent): a webhook URL, a schedule, a polling check on an app, or a push event from an app. Triggers deliver through a durable inbox so events are not lost. See [Triggers](../04-triggers/overview.md).

## Agent

An **agent** is a conversational AI with instructions, a model, and tools. Tools can be connector actions, workflows, skills, and sub-agents. Conversations are **sessions** made of **messages**. See [Agents](../05-agents/overview.md).

## Skill

A **skill** is a reusable bundle of instructions, reference files and scripts that agents can load when relevant. Skills can sync from external sources.

## Connector and connection

A **connector** is the definition of an integration (Slack, Gmail, ...). A **connection** (a connector credential) is your authorized account for it, stored encrypted. See [Connectors](../07-connections/connectors.md).

## Secret

A **secret** is an encrypted value (token, password) you reference from nodes without exposing it. See [Secrets](../07-connections/secrets.md).

## Artifact

An **artifact** is a file produced or consumed by runs and agents (reports, PDFs, spreadsheets, images). Artifacts can be shared. See [Artifacts](../09-content/artifacts.md).

## Credits

**Credits** meter usage. Runs, AI calls and some integrations consume credits. Plans include a monthly allowance; you can buy credit packs or enable overage. See [Credits](../11-billing/credits.md).

## Internal vs Public API

- The **Internal API** (`/api/v1`) is used by the Agent1o1 web app and authenticated with user sessions.
- The **Public API** (`/api/public/v1`) is for your code, authenticated with workspace **API keys** that carry scoped abilities.
