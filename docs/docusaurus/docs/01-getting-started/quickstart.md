---
title: Quickstart
description: Build, publish and run your first workflow in about ten minutes.
sidebar_position: 2
---

# Quickstart

In this guide you create a workflow that takes some text, summarizes it with AI, and returns the result. Then you run it from the app and from the API.

## 1. Sign up and create a workspace

1. Open the app and choose **Sign up** (email and password, Google, or GitHub).
2. Confirm your email using the link we send.
3. Complete [onboarding](./onboarding.md). A workspace is created for you.

## 2. Create a workflow

1. Go to **Playbooks** (the workflows list) and choose **New workflow**.
2. Name it `Summarize text`.

## 3. Add nodes

1. Add a **Workflow Input** node. Add one field named `text` of type text.
2. Add an **AI Summarize** node and connect `text` to its input.
3. Add a **Workflow Output** node and connect the summary to it.

:::note
The editor only lets you connect ports whose types are compatible. If a connection is refused, add a conversion node or change the port type.
:::

## 4. Test and publish

1. Choose **Run** and enter some text. Watch each node turn green and open the node's output.
2. Choose **Publish**. A new version is created. If your workspace has a [publish approval policy](../02-workflows/versions-and-publishing.md), an admin must approve it first.

## 5. Run it from the API

1. Go to **Settings → API keys** and create a key with the `workflows:write` and `runs:read` abilities. Copy it now; it is shown once.
2. Start a run:

```bash
curl -X POST https://api.example.com/api/public/v1/workflows/WORKFLOW_ID/runs \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"inputs": {"text": "Long text to summarize..."}}'
```

3. Poll the run:

```bash
curl https://api.example.com/api/public/v1/runs/RUN_ID \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Next steps

- Start it automatically with a [trigger](../04-triggers/overview.md).
- Give an [agent](../05-agents/overview.md) your workflow as a tool.
- Browse [templates](../09-content/templates.md) for ready-made workflows.
