---
title: Data types and mapping
description: Port types, type checking at save time, conversions, and referencing values from earlier nodes.
sidebar_position: 3
---

# Data types and mapping

## Port types

| Type | Meaning |
|---|---|
| `text` | A single string |
| `list_text` | A list of strings |
| `list_list_text` | A list of lists, such as table rows |
| `file` | A file reference (artifact) |
| `boolean` | True or false |
| `number` | A number |

## Type checking

Types are checked when you **save and publish**, not only when you run. Each edge's source and target ports must be compatible. An invalid graph cannot be published, so a published workflow does not fail on a type mismatch at run time.

When a single item is expected and a list arrives, the engine loops (fan-out). When you need an explicit conversion, use a node such as **Array**, **String** or **Table**.

## Referencing values

Inside text fields, reference earlier outputs with the template syntax. The syntax is the same everywhere a text field accepts it, including trigger conditions and agent tool settings.

## Comparing values

The Filter node, Router node, and trigger conditions share one condition engine, so an operator means the same thing everywhere.

| Group | Operators |
|---|---|
| Equality | `equals`, `not_equals` |
| Text | `contains`, `not_contains`, `starts_with`, `ends_with`, `matches_regex` |
| Numeric | `greater_than`, `greater_or_equal`, `less_than`, `less_or_equal` |
| Membership | `in_list` |
| Presence | `is_empty`, `is_not_empty` |
| Boolean | `is_true`, `is_false` |

Rules worth knowing:

- Numbers and numeric strings compare by value (`"5"` equals `5`). Everything else compares as exact text.
- `is_empty` is true only for null, blank text and an empty list. `0`, `"0"` and `false` are values.
- `contains` also searches inside a list.
- Ordering operators need two numbers, otherwise they are false.
- An unknown operator is an error and never silently passes.
- `matches_regex` patterns are limited to 200 characters and must compile.

## Secrets in fields

Reference a stored [secret](../07-connections/secrets.md) instead of pasting tokens into a node. Secrets are resolved at run time and never shown in logs.
