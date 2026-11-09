---
title: The canvas and editor
description: Building a workflow on the visual canvas, with the AI builder and autosave.
sidebar_position: 2
---

# The canvas and editor

The editor is a node canvas (React Flow). Drag nodes from the library, connect ports, configure each node in the side panel.

## Basics

| Action | How |
|---|---|
| Add a node | Open the node library, search, drag it in (or click) |
| Connect | Drag from an output port to a compatible input port |
| Configure | Select a node and fill in its settings |
| Move and arrange | Drag nodes. **Auto layout** arranges the graph |
| Delete | Select and press Delete |
| Undo / redo | Standard shortcuts |
| Test one node | Pin sample output on upstream nodes, then run |

The editor saves automatically. Concurrent edits are detected, and a conflict message appears if someone else changed the graph.

## Mapping values between nodes

Wire an output to an input with an edge, or reference earlier values inside text fields with the template syntax. See [Data types and mapping](./data-types-and-mapping.md).

## Workflow interface

**Workflow Input** and **Workflow Output** nodes define the interface other systems see: names, types, labels, defaults. Field types are `string`, `text`, `number`, `boolean`, `select` and `json`. The interface is used by:

- the **Run** form,
- the API (`GET /workflows/{id}/interface`),
- [sub-workflows](./flow-control.md#sub-workflows) and agent tools,
- [public pages](../09-content/pages.md).

## AI workflow builder

The builder is a chat panel that creates and edits the graph for you.

- **Build mode** changes the canvas. **Ask mode** answers questions without changing anything.
- Each builder conversation is a session: `active`, `promoted` or `archived`.
- Message and assist calls are rate limited (10 and 20 per minute).
- Changes are proposed against the current graph, and a conflict is reported if the graph changed in the meantime.

Requires the `workflow.builder.use` permission.

## Keyboard and productivity tips

- Use search in the library: nodes are indexed by name, category, and app.
- Duplicate a node group by copying and pasting.
- Rename nodes so output references stay readable.
