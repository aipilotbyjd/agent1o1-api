---
title: Data nodes
description: Reference for data transform nodes — parsing, formatting, scraping, calling APIs and storing data.
sidebar_position: 3
---

# Data nodes

| Node | What it does |
|---|---|
| **Call API** | Generic HTTP request. A GET never sends a body. Uses a guarded client. |
| **Transform** | Reshape data with mapping rules. |
| **Set Variable** | Store a value to reuse later in the run. |
| **JSON** | Parse, build and query JSON. |
| **Array** | Create, slice, sort, dedupe, join lists. |
| **String** | Split, replace, trim, case conversion, templating. |
| **Math** | Arithmetic and rounding. |
| **Date and Time** | Parse, format, add and compare dates, with timezones. |
| **Table** and **Table Format** | Work with rows and columns, output Markdown or HTML tables. |
| **CSV Parse** | Turn CSV text into rows. |
| **Lookup** | Match a value against a table. |
| **Pure Data** | Constant values. |
| **Data Store** | Persistent key-value storage across runs. |
| **Web Scrape** | Fetch a page and extract content. |
| **Page to Markdown** | Convert a web page to clean Markdown. |
| **HTML to Text** | Strip HTML to text. |
| **RSS Read** | Read items from a feed. |
| **PDF Create** | Render content to a PDF artifact. |
| **PII Redact** | Detect and mask personal data. |
| **Run Code** | Limited, whitelisted operations (set, copy, uppercase, lowercase, concat). Arbitrary code execution is not available. |

:::warning
**Run Code** is intentionally a safe whitelist. Arbitrary code execution needs a separate security review before it is offered.
:::

## Files

Nodes that create files (PDF, spreadsheets, documents, images) output **artifacts**. Spreadsheets use PhpSpreadsheet, Word documents use PhpWord, PDFs use Dompdf.
