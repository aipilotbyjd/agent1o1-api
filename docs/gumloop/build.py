#!/usr/bin/env python3
import argparse
import json
import re
import time
from pathlib import Path
from urllib.parse import urlparse
import requests
from bs4 import BeautifulSoup

BASE = "https://docs.gumloop.com"
INDEX_URL = BASE + "/llms.txt"

session = requests.Session()
session.headers.update({
    "User-Agent": "GumloopDocsKnowledgeBaseBuilder/1.0 (documentation research)"
})

def slugify(s):
    s = re.sub(r"[^a-zA-Z0-9._/-]+", "-", s).strip("-")
    return s or "document"

def parse_index(text):
    pages = []
    for line in text.splitlines():
        m = re.match(r"- \[(.*?)\]\((https://docs\.gumloop\.com/[^)]+)\)(?::\s*(.*))?$", line.strip())
        if not m:
            continue
        title, url, description = m.groups()
        # Ignore non-document artifacts unless explicitly useful.
        path = urlparse(url).path
        if path.endswith((".md", ".txt", ".yaml")):
            pages.append({
                "title": title,
                "url": url,
                "description": description or "",
                "path": path,
            })
    # De-duplicate by URL while preserving order.
    seen, result = set(), []
    for p in pages:
        if p["url"] not in seen:
            seen.add(p["url"])
            result.append(p)
    return result

def category_from_path(path):
    parts = [p for p in path.split("/") if p]
    if len(parts) >= 2:
        return parts[0] if parts[0] != "index.md" else "root"
    return "root"

def markdown_to_clean_text(md):
    md = md.replace("\r\n", "\n").replace("\r", "\n")
    # Remove common Mintlify navigation boilerplate while keeping content.
    md = re.sub(r"(?m)^\s*Copy page\s*$", "", md)
    md = re.sub(r"(?m)^\s*Was this page helpful\?\s*$", "", md)
    md = re.sub(r"(?m)^\s*Yes No\s*$", "", md)
    # Collapse excessive whitespace.
    md = re.sub(r"\n{3,}", "\n\n", md)
    return md.strip()

def split_sections(md):
    lines = md.splitlines()
    sections = []
    current = []
    heading_path = []
    for line in lines:
        if re.match(r"^#{1,6}\s+", line):
            if current:
                sections.append(("\n".join(current).strip(), list(heading_path)))
                current = []
            level = len(line) - len(line.lstrip("#"))
            title = re.sub(r"^#{1,6}\s+", "", line).strip()
            heading_path = heading_path[:level-1]
            heading_path.append(title)
        current.append(line)
    if current:
        sections.append(("\n".join(current).strip(), list(heading_path)))
    return [(t, h) for t, h in sections if t]

def make_chunks(text, heading_path, size, overlap):
    # Character-based chunking is intentionally deterministic and dependency-free.
    if len(text) <= size:
        return [text]
    chunks = []
    start = 0
    while start < len(text):
        end = min(len(text), start + size)
        if end < len(text):
            boundary = max(
                text.rfind("\n\n", start, end),
                text.rfind("\n", start, end),
                text.rfind(" ", start, end),
            )
            if boundary > start + int(size * 0.55):
                end = boundary
        chunk = text[start:end].strip()
        if chunk:
            chunks.append(chunk)
        if end >= len(text):
            break
        start = max(end - overlap, start + 1)
    return chunks

def fetch(url, retries=3):
    last = None
    for attempt in range(retries):
        try:
            r = session.get(url, timeout=45)
            r.raise_for_status()
            return r.text
        except Exception as e:
            last = e
            time.sleep(1.5 * (attempt + 1))
    raise last

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--output", default="output")
    ap.add_argument("--chunk-size", type=int, default=1200)
    ap.add_argument("--chunk-overlap", type=int, default=150)
    ap.add_argument("--delay", type=float, default=0.15)
    args = ap.parse_args()

    out = Path(args.output)
    raw = out / "raw"
    raw.mkdir(parents=True, exist_ok=True)

    print("Fetching official documentation index...")
    llms = fetch(INDEX_URL)
    (out / "llms.txt").write_text(llms, encoding="utf-8")

    pages = parse_index(llms)
    print(f"Discovered {len(pages)} documentation entries.")

    manifest = []
    documents_path = out / "documents.jsonl"
    chunks_path = out / "chunks.jsonl"

    with documents_path.open("w", encoding="utf-8") as docs_f, \
         chunks_path.open("w", encoding="utf-8") as chunks_f:

        for i, page in enumerate(pages, 1):
            url = page["url"]
            path = page["path"]
            if path.endswith(".yaml"):
                # Save OpenAPI specs separately; don't treat them as Markdown docs.
                local = raw / "openapi" / Path(path).name
            elif path.endswith(".txt"):
                local = raw / "text" / Path(path).name
            else:
                local = raw / path.lstrip("/")
            local.parent.mkdir(parents=True, exist_ok=True)

            try:
                body = fetch(url)
                local.write_text(body, encoding="utf-8")
                clean = markdown_to_clean_text(body)
                category = category_from_path(path)
                doc_id = f"gumloop:{i:04d}:{slugify(path)}"

                doc = {
                    "id": doc_id,
                    "title": page["title"],
                    "description": page["description"],
                    "category": category,
                    "source_url": url,
                    "source_path": path,
                    "local_path": str(local.relative_to(out)),
                    "content": clean,
                }
                docs_f.write(json.dumps(doc, ensure_ascii=False) + "\n")

                section_chunks = []
                for section_text, heading_path in split_sections(clean):
                    section_chunks.extend(
                        (c, heading_path)
                        for c in make_chunks(
                            section_text,
                            heading_path,
                            args.chunk_size,
                            args.chunk_overlap,
                        )
                    )

                for ci, (chunk, heading_path) in enumerate(section_chunks):
                    record = {
                        "id": f"{doc_id}:chunk:{ci:04d}",
                        "document_id": doc_id,
                        "title": page["title"],
                        "category": category,
                        "source_url": url,
                        "source_path": path,
                        "heading_path": heading_path,
                        "chunk_index": ci,
                        "text": chunk,
                    }
                    chunks_f.write(json.dumps(record, ensure_ascii=False) + "\n")

                manifest.append({
                    **page,
                    "id": doc_id,
                    "category": category,
                    "local_path": str(local.relative_to(out)),
                    "status": "ok",
                })
                print(f"[{i}/{len(pages)}] OK  {page['title']}")
            except Exception as e:
                manifest.append({
                    **page,
                    "category": category_from_path(path),
                    "status": "error",
                    "error": str(e),
                })
                print(f"[{i}/{len(pages)}] ERROR {page['title']}: {e}")

            time.sleep(args.delay)

    (out / "manifest.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False),
        encoding="utf-8"
    )

    # Human-readable sitemap.
    by_cat = {}
    for p in manifest:
        by_cat.setdefault(p.get("category", "other"), []).append(p)
    lines = ["# Gumloop Documentation Sitemap", ""]
    for cat in sorted(by_cat):
        lines += [f"## {cat}", ""]
        for p in by_cat[cat]:
            status = p.get("status", "")
            lines.append(f"- [{p['title']}]({p['url']}) — `{status}`")
        lines.append("")
    (out / "sitemap.md").write_text("\n".join(lines), encoding="utf-8")

    print("\nDone.")
    print(f"Documents: {documents_path}")
    print(f"Chunks:    {chunks_path}")
    print(f"Manifest:  {out / 'manifest.json'}")

if __name__ == "__main__":
    main()
