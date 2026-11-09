---
title: Evals
description: Test an agent with suites of cases and assertions before changing it.
sidebar_position: 7
---

# Evals

Evals are regression tests for agents.

- **Suite**: a named group of cases.
- **Case**: a prompt (and optional context) plus **assertions** on the answer or tool calls.
- **Run**: executes every case and records pass/fail per assertion, cost and duration.

Runs start manually (`POST /eval-suites/{suite}/runs`) or from automation. Each run has a trigger and a status.

## Assertions

Common types: contains text, does not contain text, calls a tool, does not call a tool, matches a rubric judged by a model. See `app/Enums/Agents/EvalAssertionType.php` for the full list.

## Workflow

1. Add cases for the behaviors you care about, including failure cases.
2. Run the suite before and after a prompt or tool change.
3. Compare results. Restore the previous agent version if quality dropped.

Endpoints: `/agents/{agent}/eval-suites` and nested `/cases`, `/runs`.
