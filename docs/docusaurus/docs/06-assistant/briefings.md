---
title: Briefings
description: Daily briefings and meeting prep delivered automatically.
sidebar_position: 2
---

# Briefings

Daily briefings and meeting prep share one engine. Each briefing is a **run** that gathers data from your connected calendar, mail and tasks, then writes a summary.

## Daily briefing

- Choose a delivery time and timezone, and which sources to include.
- A run creates a briefing with highlights, schedule, open follow-ups and situations.
- Due briefings are checked every minute (`assistant:run-due-briefings`).

## Meeting prep

- Meetings are synced from your calendar every 10 minutes.
- Before a meeting, prep collects attendee context, related email and past notes.
- Prep status moves through pending, ready, delivered or failed.

## Situations

Longer-running matters (for example "contract with Acme") are tracked as **situations** with steps and statuses. They appear in briefings until resolved.

## Controls

Enable or disable each briefing type, change the time, and give feedback. Feature flags `ASSISTANT_FEATURE_DAILY`, `ASSISTANT_FEATURE_PREP`, `ASSISTANT_FEATURE_SITUATIONS` turn them on or off platform-wide.
