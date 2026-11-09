---
title: Workflows overview
description: What a workflow is, how it is stored, and how it runs.
sidebar_position: 1
---

# Workflows overview

A workflow is a graph of nodes. You build it on the canvas, publish it as a version, then run it manually, from the API, or from a [trigger](../04-triggers/overview.md).

## Lifecycle

```
Draft ──► Validate ──► Publish ──► Version N ──► Run
  ▲                       │
  └──── edit again ◄──────┘  (draft keeps evolving; versions are immutable)
```

1. **Draft**: the working copy. The canvas autosaves it.
2. **Validate**: the graph is checked for type errors, missing required settings, cycles, and invalid trigger conditions.
3. **Publish**: creates an immutable **version**. Optionally needs approval.
4. **Run**: always against a published version.

## How a run executes

1. A **run** is created with its trigger source and input payload.
2. The engine resolves the graph and queues one job per ready node.
3. Each job creates a **node run**, executes the node, stores its output, records its credit cost, and releases the nodes downstream.
4. When a list reaches a node that expects a single item, the engine **fans out** into one node run per item and joins them later (see [Loops and batches](./flow-control.md)).
5. The run finishes as `completed`, `failed` or `cancelled`. Failures can be routed to an alternate path instead of failing the whole run.

Nodes are executed by queue workers, so long runs never block the web app. See [Queues](../13-self-hosting/queues-and-horizon.md).

## Organizing workflows

- **Folders** group workflows and agents. Move items in bulk.
- **Tags** label items and filter lists.
- **Favorites** and **pinned node output** (pin a node's sample output for testing).
- **Trash**: deleted workflows go to the trash and can be restored or permanently deleted.
- **Duplicate** copies a workflow with its graph.

## Plan limits

The number of workflows, active triggers, concurrent runs, and maximum credits per run depend on your [plan](../11-billing/plans.md).

## Related

- [The canvas and editor](./canvas-and-editor.md)
- [Data types and mapping](./data-types-and-mapping.md)
- [Runs and logs](./runs-and-logs.md)
- [Versions and publishing](./versions-and-publishing.md)
