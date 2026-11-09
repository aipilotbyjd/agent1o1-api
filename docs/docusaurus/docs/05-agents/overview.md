---
title: Agents overview
description: Conversational, tool-using AI agents built on Laravel AI.
sidebar_position: 1
---

# Agents overview

An **agent** is an AI with instructions, a model and a set of tools. You chat with it, trigger it, call it from the API, or embed it in a workflow with the **Agent** node.

## What an agent has

| Part | Purpose |
|---|---|
| **Instructions** | The system prompt. An **Improve instructions** action rewrites a draft prompt. |
| **Model** | Chosen from the [model catalog](../08-ai-models/model-catalog.md) |
| **Tools** | Connector actions, workflows, skills, sub-agents ([Tools](./tools-and-bindings.md)) |
| **Autonomy mode** | How much it may do without asking ([Autonomy](./autonomy-and-approvals.md)) |
| **Knowledge** | Collections it can search ([Knowledge base](./knowledge-base.md)) |
| **Memory** | Facts it keeps across sessions ([Memory and reflection](./memory-and-reflection.md)) |
| **Evals** | Test suites for quality ([Evals](./evals.md)) |
| **Versions** | Immutable history you can restore |

## Sessions and messages

A **session** is one conversation. Each user or agent turn is a **message**. Sessions can be listed, renamed and deleted. Tool calls are logged as steps and metered the same way as node runs.

Endpoints (internal): `/workspaces/{workspace}/agents/{agent}/sessions` and `.../messages`, plus `.../turns` for streaming turns. Public API: see [Public API](../12-api/public-api.md).

## Turn loop

1. A message arrives.
2. The model reads the instructions, memory, knowledge hits and tool list.
3. It may call tools. Each call passes the **autonomy gate**: allowed, asked, or blocked.
4. Results return to the model. Steps repeat until a final answer.
5. Credits are deducted per step. A cost cap stops runaway turns.

Long turns use a dedicated queue (`ai-agent`) with limited concurrency to stay under provider rate limits.

## Calling an agent from workflows

Use the **Agent** node to run a session inside a workflow, or **AI Route to Agent** to pick one dynamically.

## Create an agent

1. **Agents → New agent**. Write instructions.
2. Pick a model.
3. Add tools and skills.
4. Set autonomy.
5. Chat with it in the preview. Fix instructions. Add evals.
6. Share it by API, workflow node, trigger or Slack.

Plan limits: Free allows 1 agent, Starter 10, Pro and Business unlimited.

## Draft agents

`POST /agents/draft` creates an agent from a short description so you can refine it afterwards.
