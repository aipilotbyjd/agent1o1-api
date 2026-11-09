---
title: Trigger conditions
description: Filter events with "Only run when" rules before a run starts.
sidebar_position: 5
---

# Trigger conditions

Every trigger can carry an **Only run when** list. Each rule has a `source`, a `path` into the event, an `operator`, and a `value`. All rules must pass.

Events that fail the rules are stored with status `ignored` and no run starts, so you can still see why.

Operators are the same as in the Filter and Router nodes. See [Data types and mapping](../02-workflows/data-types-and-mapping.md#comparing-values).

## Validation at publish time

Publishing rejects a trigger whose conditions contain:

- an unknown operator,
- a missing path,
- a bad `source`,
- a `matches_regex` pattern that does not compile or exceeds 200 characters.

Patterns run with a fixed delimiter and the Unicode flag, so modifiers cannot be injected.

## Example

Only run for paid, high-value orders:

| Path | Operator | Value |
|---|---|---|
| `body.status` | `equals` | `paid` |
| `body.total` | `greater_or_equal` | `100` |
