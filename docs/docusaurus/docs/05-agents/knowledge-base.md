---
title: Knowledge base
description: Give agents and workflows searchable documents using embeddings.
sidebar_position: 5
---

# Knowledge base

The knowledge base stores documents as **embeddings** so agents and workflows can search by meaning.

## Sources

| Source type | Notes |
|---|---|
| Uploaded files and text | Added directly |
| App sources | Google Drive, Notion and others via connections. Browse available apps at `/knowledge-base/sources/apps`. |
| Web pages | Crawl and sync |

Sources sync on demand (`POST /sources/{source}/sync`) and automatically every 15 minutes. Each source reports a status (pending, syncing, ready, failed), and you can list its documents.

## Collections and modes

Documents belong to **collections**. Attach a collection to an agent in one of two modes:

| Mode | Behavior |
|---|---|
| **Search** | The agent calls a `search knowledge` tool when it needs facts |
| **Always include** | The collection is injected into every turn |

## Search

- API: `POST /workspaces/{workspace}/knowledge-base/search`.
- Node: **Knowledge Search**. Save with **Knowledge Save**.

## Embedding provider

Configured with `AI_EMBEDDINGS_PROVIDER`. Supported providers include OpenAI, Gemini, Jina, VoyageAI and Cohere. Changing provider or dimensions requires re-embedding.

## Cost

Embedding and search calls consume credits.
