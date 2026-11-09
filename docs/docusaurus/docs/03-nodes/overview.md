---
title: Node catalog overview
description: How nodes are organized, how they are configured, and how they are billed.
sidebar_position: 1
---

# Node catalog overview

Nodes are grouped into categories. The node library in the editor mirrors this list.

| Category | Slug | Page |
|---|---|---|
| Triggers and events | `triggers-events` | [Triggers and I/O](./triggers-and-io.md) |
| AI automation | `ai-automation` | [AI nodes](./ai-nodes.md) |
| Data transform | `data-transform` | [Data nodes](./data-nodes.md) |
| Flow logic | `flow-logic` | [Flow control](../02-workflows/flow-control.md) |
| Inputs and outputs | `inputs-outputs` | [Triggers and I/O](./triggers-and-io.md) |
| Integrations (one category per app) | e.g. `google-sheets` | [Integrations](./integrations.md) |
| Custom | `custom` | [Custom nodes](./custom-nodes.md) |

## Anatomy of a node

- **Inputs**: typed ports that receive data from edges.
- **Settings**: fields configured in the side panel. Some are dropdowns filled live from the connected app (for example, choose a Slack channel or a Google Sheet).
- **Outputs**: typed ports that feed downstream nodes.
- **Effect**: nodes declare whether they only read, write, or have side effects. Agents use this for approval decisions.
- **Connection**: integration nodes need a [connection](../07-connections/connectors.md).

## Cost

Each node run records a credit cost. A run has a base cost; AI nodes are billed by tokens and the model's price; some integration nodes have fixed costs. See [Credits](../11-billing/credits.md).

## Reliability notes

- **Call API** and other outbound HTTP nodes go through a guarded client that blocks private addresses (SSRF protection), pins DNS, caps response size, and does not replay headers across origins.
- Files move between nodes as artifacts. Integration nodes that accept inline file content use text or base64 with a size cap (typically 10 MB).
- Dropdown values accept ids or names. Some nodes (for example Linear and Jira) resolve names to ids with one lookup, and fail loudly if a name matches several people.
