---
title: Starting playbooks automatically
description: Run playbooks on a schedule, from a web address, or when something happens in an app.
sidebar_position: 6
---

# Starting playbooks automatically

A **trigger** starts a playbook for you. Add one as the first step of the playbook, or manage them all in **Settings → Triggers**.

## Kinds of trigger

| Trigger | Starts when | Example |
|---|---|---|
| **Schedule** | A time comes round | Every weekday at 9:00 |
| **Webhook** | Another system sends a web request to your playbook's address | Your website posts a new order |
| **App event** | Something happens in a connected app | A new Typeform response, a Stripe payment, a Slack message |
| **Polling** | Agent1o1 checks an app every few minutes and finds something new | A new row in Google Sheets |

### Schedule

Pick a preset or write your own schedule, and choose the time zone. Schedules use the time zone you pick, so daylight saving is handled for you.

### Webhook

Each webhook trigger has its own address. Copy it from **Settings → Triggers** (there is also a ready-made test command you can copy). Anything that can send a web request can start the playbook. Treat the address like a password. If it leaks, rotate it and the old address stops working immediately.

### App events

Pick the app and the event. Some apps tell us instantly. Others are checked on a timer, and the trigger tells you which. How often we can check depends on your plan.

## Only run when

You can add conditions so a trigger ignores events you don't care about. For example, only run when the status is "paid" and the total is at least 100. Events that don't match are kept in the trigger's history as "ignored", so you can see why nothing ran.

## Controls

| Control | What it does |
|---|---|
| On/off switch | Pause a trigger without deleting it |
| Skip if running | Ignore new events while the last run is still going |
| Failure protection | Switches a trigger off after repeated failures, and tells you why |
| Runs per minute | Caps how fast runs start |
| Grouping | Collects several events into one run |

## Test and recover

- **Test** a trigger to start a run as if an event had arrived, and copy a sample of the data it provides.
- Open a trigger's history to see every event it received.
- **Replay** an event that failed.

## Plan limits

How many triggers can be on at once, and how often an app can be checked, depends on your [plan](../08-billing/plans.md).
