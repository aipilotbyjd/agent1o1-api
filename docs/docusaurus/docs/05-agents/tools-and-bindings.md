---
title: Tools and bindings
description: Give agents connector actions, workflows, skills and sub-agents, and lock parameters safely.
sidebar_position: 2
---

# Tools and bindings

Tools are everything an agent can do.

| Tool kind | Source | Example |
|---|---|---|
| **Connector tool** | An app node attached as a tool | Send a Slack message, create a Jira issue |
| **Workflow tool** | A published workflow with its interface as the schema | "Generate monthly report" |
| **Skill** | Instructions and references loaded when relevant | "Brand voice guide" |
| **Sub-agent** | Another agent delegated a task | Research agent |
| **Knowledge search** | Search over knowledge collections | Product docs |

## Tool bindings

A **tool binding** attaches a tool to an agent and controls what the model can fill in.

- **Bound values**: fixed by you. The model cannot change them (for example the Slack channel).
- **Exposed fields**: the model may fill these in.
- Each binding stores which connection to use.

This is the main security control: bind the account, folder or channel, and expose only the free-text fields.

Endpoints: `/agents/{agent}/tool-bindings` (list, create, update, delete), `/agents/{agent}/workflows`, `/skills`, `/subagents`, `/knowledge`.

## Workflow tools

Attach a workflow with `POST /agents/{agent}/workflows/{workflow}`. The workflow's input interface becomes the tool's parameters. The agent calls it synchronously, or async with a callback for long workflows.

## Sub-agents

Delegate work to another agent. Sub-agent tasks run on their own queue (`ai-subagent`) and appear in the session's **subagent tasks**.

## Effects

Each tool declares an effect: read, write, or external side effect. The effect feeds the [autonomy gate](./autonomy-and-approvals.md).
