# AI Knowledge Base Schema

## documents.jsonl

One JSON object per source document.

```json
{
  "id": "gumloop:0001:core-concepts/agents.md",
  "title": "Agents",
  "description": "...",
  "category": "core-concepts",
  "source_url": "https://docs.gumloop.com/core-concepts/agents.md",
  "source_path": "/core-concepts/agents.md",
  "local_path": "raw/core-concepts/agents.md",
  "content": "..."
}
```

## chunks.jsonl

Designed for embedding/vector search.

```json
{
  "id": "gumloop:0001:core-concepts/agents.md:chunk:0000",
  "document_id": "gumloop:0001:core-concepts/agents.md",
  "title": "Agents",
  "category": "core-concepts",
  "source_url": "https://docs.gumloop.com/core-concepts/agents.md",
  "source_path": "/core-concepts/agents.md",
  "heading_path": ["Agents", "Tools"],
  "chunk_index": 0,
  "text": "..."
}
```

## Suggested metadata filters

- `category`
- `title`
- `source_path`
- `heading_path`

## Suggested retrieval strategy

1. Embed `chunks.jsonl.text`.
2. Store `source_url` as citation metadata.
3. Retrieve top-k semantic matches.
4. Optionally combine vector search with keyword/BM25 search.
5. Rerank results.
6. Pass only relevant chunks to your coding agent.

For a workflow-builder clone, consider maintaining separate collections:

- `gumloop-core`
- `gumloop-nodes`
- `gumloop-mcp`
- `gumloop-api`
- `gumloop-help`
- `gumloop-enterprise`

This allows your coding agent to retrieve implementation-specific knowledge without
loading the entire documentation corpus into every prompt.
