---
title: Versions and publishing
description: Immutable versions, restoring, and the publish approval workflow.
sidebar_position: 6
---

# Versions and publishing

## Versions

Publishing a workflow creates an immutable **version**. Runs use a version, so editing the draft never changes something that is already running.

- List versions: `GET /workspaces/{workspace}/workflows/{workflow}/versions`
- Open one to inspect its graph.
- **Restore** a version to make it the current draft/published state.

## Publishing

Choose **Publish** in the editor, or call `POST /workflows/{workflow}/versions`. The server validates the graph first and returns every error. Typical errors:

- a required node setting is empty,
- two ports have incompatible types,
- a trigger condition has an unknown operator, a missing path, or an invalid regex,
- a referenced secret or connection is missing.

## Publish approval

Workspaces can require approval before a version goes live.

1. An admin sets a **publish approval policy**.
2. An editor submits a **publish request**.
3. A reviewer **approves** or **rejects**; the requester can **withdraw**.
4. On approval, the version is published.

Statuses: pending, approved, rejected, withdrawn. Permissions: `workflow.publish` to request, `workflow.approve-publish` to review. Feature flag: `workflow_approvals`.

## Change management tips

- Keep one trigger per workflow when you can. It makes history easier to read.
- Use **tags** such as `prod` or `experimental`.
- Test with pinned sample output before publishing.
