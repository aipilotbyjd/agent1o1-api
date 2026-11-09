---
title: Flow control
description: Filters, routers, loops, waits, approvals, joins, sub-workflows and error handling.
sidebar_position: 4
---

# Flow control

## Filter and Router

- **Filter** passes data through only when a condition is met.
- **Router** evaluates conditions and sends the run down the matching branch. It falls back to the `default` branch when nothing matches. Unary operators (`is_empty`, `is_true`) need no value.
- **Switch** chooses a branch by comparing one value to a list of cases.

## Loops and batches

- **Loop** processes a list one item at a time. `foreach` mode runs the loop body per item; `map` mode transforms each item.
- **Batch** groups items into chunks.
- When a list feeds a node that expects one item, the engine creates one node run per item and a **Join Paths** node merges results.

Loops are bounded by plan limits on concurrent runs and credits per run.

## Join Paths

Waits for upstream branches and merges them into one path.

## Delay and Wait

- **Delay** pauses for a number of seconds. Maximum 7 days; negatives are treated as 0.
- **Wait** pauses until an external system calls the run's **callback URL** (`POST /api/hooks/wait/{token}`). The JSON body becomes the node's output. With a timeout, a timeout fails the node (connect an `error` edge) unless `continue_on_timeout` is on, in which case the run continues with `{"timed_out": true}`. The run status is `awaiting_callback` while waiting.

Use Wait for anything longer than a Delay.

## Human approval

The **Human Approval** node pauses the run (`awaiting_approval`) and asks someone to approve or reject.

- Approvers decide in the app (**Approvals** page) or by API (`POST /runs/{run}/approvals/{approval}/decide`).
- The outcome selects the approved or rejected branch.
- Requires the `workflow_approvals` plan feature.

## Sub-workflows

A **Sub-workflow** node runs another workflow as a step. Inputs and outputs map through the child's interface. Child runs are linked to the parent run.

## Error handling

- Nodes retry with backoff on transient failures before the node run is marked `failed`.
- Connect an **error** edge from a node to a recovery path. The run continues instead of failing.
- Typed domain errors (insufficient credits, plan limit, missing secret, provider paused) return consistent API error shapes. See [Errors](../12-api/errors-and-limits.md).
- **Retry** a failed run from the app or `POST /runs/{run}/retry`.
- **Cancel** an active run with `POST /runs/{run}/cancel`.

## Guardrails

| Guardrail | Purpose |
|---|---|
| Max concurrent runs per plan | Stops one workspace flooding the queue |
| Max credits per run | Stops a runaway loop spending your balance |
| Skip-if-running (triggers) | Avoids overlapping runs of the same trigger |
| Failure breaker (triggers) | Disables a trigger after repeated failures |
