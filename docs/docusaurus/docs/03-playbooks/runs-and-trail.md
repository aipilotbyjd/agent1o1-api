---
title: Runs and the Trail
description: See what ran, what happened in each step, how many credits it used, and fix failures.
sidebar_position: 7
---

# Runs and the Trail

The **Trail** shows every run of your playbooks and agents in the workspace.

## Reading a run

Open any run to see:

- **Status** and when it started and finished
- **What started it**: you, a schedule, a webhook, an app event, an agent, or a public page
- **Each step**: what it received, what it produced, how long it took and any error
- **Credits used**, in total and per step
- **Files created** during the run

## Statuses

| Status | Meaning |
|---|---|
| Pending | Waiting to start |
| Running | In progress |
| Waiting for approval | Paused for a person. See [Approvals](./approvals.md) |
| Waiting for callback | Paused until another system continues it |
| Completed | Finished successfully |
| Failed | A step failed and nothing handled it |
| Cancelled | Someone stopped it |

## Fix a failed run

1. Open the failed run and select the step marked with an error.
2. Read the message. Common causes: an app needs to be reconnected, a required setting is empty, or you've run out of credits.
3. Fix the cause, then choose **Retry**. Steps that already finished are not repeated.

## Stop a run

Open a running run and choose **Cancel**.

## Live updates

Runs update live on screen. You don't need to refresh.

## How long history is kept

Old run details are removed after a while. Download any files you need to keep.
