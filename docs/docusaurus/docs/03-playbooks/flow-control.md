---
title: Control the flow
description: Filters, routers, loops, waits, approvals and error handling.
sidebar_position: 4
---

# Control the flow

## Filter

Lets information continue only if a condition is true. For example, only continue when the order total is above 100.

## Router

Sends the run down a different path depending on conditions. For example, billing questions go one way and technical questions another. If nothing matches, the run takes the **default** path.

## Conditions

Filters, routers and triggers all use the same conditions:

| Kind | Options |
|---|---|
| Equality | equals, does not equal |
| Text | contains, does not contain, starts with, ends with, matches a pattern |
| Numbers | greater than, at least, less than, at most |
| Lists | is in list |
| Empty checks | is empty, is not empty |
| True or false | is true, is false |

Numbers typed as text compare as numbers ("5" equals 5). Anything else compares as exact text. "Is empty" is only true for nothing, blank text, or an empty list. The numbers 0 and false are values, not empty.

## Loop and Batch

**Loop** repeats the steps inside it for each item in a list, such as every row of a spreadsheet. **Batch** splits a long list into groups.

When a list arrives at a step that expects one item, the playbook automatically runs it once per item, and joins the results with **Join Paths**.

## Delay and Wait

- **Delay** pauses for a set time, up to 7 days.
- **Wait** pauses until another system tells the playbook to continue. You can set a timeout. When it times out you can fail the step or let the run carry on.

## Human approval

The **Human Approval** step pauses the run until a person approves or rejects. Pending requests appear on the **Approvals** page. The outcome decides which path continues. See [Approvals](./approvals.md).

## Sub-playbooks

A **Sub-workflow** step runs another playbook as one step. Use it to reuse logic across playbooks.

## When something fails

- A failed step stops that run, unless you connect an **error path** from the step to a recovery action, such as "notify me and carry on".
- Temporary errors are retried automatically.
- You can retry a failed run from the [Trail](./runs-and-trail.md).

## Safety limits

Your plan limits how many runs happen at once and how many credits a single run may use, which stops a runaway loop from spending everything.
