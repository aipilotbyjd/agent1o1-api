---
title: Custom nodes
description: Define your own reusable nodes (Pro plan feature).
sidebar_position: 6
---

# Custom nodes

Custom nodes let a workspace package a repeatable step (for example a specific internal API call) as a reusable node with its own inputs, settings and outputs.

- Requires the `custom_nodes` plan feature (Pro and above).
- Requires `node.manage` permission. Everyone with `node.view` can use them.
- Custom nodes appear in the library under the **Custom** category.
- They are versioned with the workflows that use them.

**Check:** the exact authoring model (declarative definition vs. code) should be confirmed from `app/Models/Nodes/CustomNode.php` and the nodes API before publishing detailed steps here.

## API

Endpoints live under `/api/v1/workspaces/{workspace}/nodes` (list, create, update, delete, options).
