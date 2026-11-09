---
title: Memory and reflection
description: How agents remember facts and learn from past sessions.
sidebar_position: 6
---

# Memory and reflection

## Memory

Agents keep **memories**: short facts that persist across sessions (a user's preferences, a project's naming rules). Memories can be viewed, edited, added and deleted at `/agents/{agent}/memories`.

## Reflection

**Reflection** reviews past sessions and proposes improvements. A reflection run produces **reflections**, each of a type with an **apply behavior**.

| Action | Result |
|---|---|
| **Apply** | Updates instructions, memory or trust rules |
| **Dismiss** | Ignores the suggestion |

Settings (`/reflection-settings`) control when it runs and whether suggestions apply automatically. Due runs are checked every 15 minutes. You can trigger one manually with `POST /reflection-runs`.

## Session evaluation

Sessions can be graded automatically against criteria (`/evaluation-settings`, `/session-evaluations`). Grades and data points show where an agent struggles, and feed reflection.
