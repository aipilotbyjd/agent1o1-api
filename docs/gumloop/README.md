# Gumloop Documentation Knowledge Base Builder

This package is designed to create an AI-ready local knowledge base from Gumloop's
official public documentation.

Important:
- The official documentation index is `https://docs.gumloop.com/llms.txt`.
- This package does NOT redistribute a bulk copy of Gumloop's documentation.
- Instead, it contains a reproducible downloader that retrieves the public Markdown
  documentation directly from Gumloop when you run it.
- Review Gumloop's terms/licensing before using the downloaded material in a product.

## What it creates

After running:

```bash
python build.py
```

you will get:

```text
output/
├── raw/
│   ├── core-concepts/
│   ├── nodes/
│   ├── api-reference/
│   ├── enterprise-features/
│   ├── cli/
│   ├── mcp-server/
│   ├── help/
│   └── ...
├── manifest.json
├── documents.jsonl
├── chunks.jsonl
├── sitemap.md
└── llms.txt
```

The downloader preserves the official URL, title, category, source path and raw
Markdown. It also produces normalized JSONL documents and retrieval-friendly chunks.

## Requirements

Python 3.10+

Install:

```bash
pip install -r requirements.txt
```

Run:

```bash
python build.py
```

Optional:

```bash
python build.py --chunk-size 1200 --chunk-overlap 150 --delay 0.15
```

## Recommended AI/RAG usage

Use `chunks.jsonl` for embeddings/RAG.

Each chunk contains:

- stable document id
- title
- category
- source URL
- local path
- heading path
- chunk index
- text

Keep `documents.jsonl` as the canonical metadata layer.

## Update

Run the builder again whenever you want a fresh snapshot. It reads the current
official `llms.txt`, so newly added documentation pages are discovered automatically.
