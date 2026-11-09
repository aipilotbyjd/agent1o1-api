---
title: Troubleshooting
description: Fix the most common problems with playbooks, agents, apps and billing.
sidebar_position: 1
---

# Troubleshooting

## A run failed

Open it in the **Trail**, select the step with the error, and read the message.

| You see | Try |
|---|---|
| "Reconnect needed" or the app rejected the request | Open **Apps**, test the connection, and reconnect it |
| A required setting is empty | Fill it in, then retry |
| Missing secret | Add it to the **Vault** with the exact name, or choose another |
| Out of credits | Add credits, upgrade, or turn on overage. See [Credits](../08-billing/credits.md) |
| Usage limit reached | A daily or weekly AI spending limit applies. Wait, or upgrade |
| Model not available | Choose another model, or add your own key |
| AI provider busy or paused | Try again shortly, or choose another model |

After fixing, use **Retry**.

## A run is stuck

- **Waiting for approval**: decide on the **Approvals** page.
- **Waiting for callback**: the playbook is waiting for another system. Send the callback, or cancel.

## I can't publish

Read the list of problems shown. Typical causes: an empty required setting, a connection between steps that doesn't fit, or an invalid trigger condition. If your workspace needs review, your request must be approved first.

## My trigger isn't firing

1. Is the trigger switched on?
2. Open its history. Events marked "ignored" didn't match your **Only run when** conditions.
3. For app triggers, check the connection and look for "reconnect needed".
4. Did "failure protection" switch it off? The trigger shows why.
5. Check you haven't reached the active trigger limit on your plan.

## An agent keeps asking permission

That's its autonomy setting. See [Autonomy and approvals](../04-agents/autonomy.md). An admin's workspace policy may also require it.

## An agent gives wrong answers

- Make the instructions clearer, with examples.
- Check its knowledge: remove out-of-date documents and use **Test knowledge search**.
- Add test cases and run them after each change.

## I can't find something

Look in **Trash**. Deleted items can be restored.

## A button is missing

Your role may not allow it. Ask an admin. Some features need a higher plan.

## Still stuck?

Contact support from the help menu. Include the run's link from the Trail, which tells us exactly what happened.
