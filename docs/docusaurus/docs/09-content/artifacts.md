---
title: Artifacts
description: Files created or used by runs and agents, with previews and sharing.
sidebar_position: 1
---

# Artifacts

An **artifact** is a stored file: a generated PDF, a spreadsheet, an image, an uploaded document, an agent's output.

## Using artifacts

- **Library / Artifacts page**: browse, upload, preview, download and delete.
- Nodes read artifacts as `file` ports and write new ones.
- Agents can create and read artifacts during a session.

## Sharing

Each artifact has a **general access** level, and can be shared with specific members.

| Setting | Effect |
|---|---|
| Restricted | Only people you add |
| Workspace | Any member |
| Anyone with the link | Public link (if enabled) |

Endpoints: `PATCH /artifacts/{artifact}/access`, `POST /artifacts/{artifact}/shares`, `DELETE /artifacts/{artifact}/shares/{user}`.

## Preview and download

`GET /artifacts/{artifact}/preview` for inline viewing and `GET /artifacts/{artifact}/download` for the file.

## Storage

Files are stored with Laravel's filesystem. Configure S3 or an S3-compatible disk in production (`FILESYSTEM_DISK`, `AWS_*`).

## Permissions

`artifact.view` and `artifact.manage`.
