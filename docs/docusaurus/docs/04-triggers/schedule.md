---
title: Schedule triggers
description: Run workflows on a cron schedule with timezone support.
sidebar_position: 3
---

# Schedule triggers

Schedule triggers run on a cron expression in a timezone you choose.

- Configure with presets (every hour, every weekday at 9am) or a custom cron expression.
- Timezone is stored with the trigger, so daylight saving changes behave as expected.
- The platform checks due triggers every minute (`triggers:run-due`).
- Skip-if-running prevents overlap when a run takes longer than the interval.

:::note
The shortest schedule interval is one minute. Polling frequency (not cron) is what the plan's minimum interval limits.
:::

## Timezones

The timezone list is available from `GET /api/v1/timezones`. Your profile timezone is the default.
