---
title: Channels
description: Talk to your assistant from Slack, email, SMS and webhooks.
sidebar_position: 4
---

# Channels

| Channel | Setup | Plan |
|---|---|---|
| **Web app** | Always available | All |
| **Slack DM** | Install the Slack app (OAuth). Events arrive at `/api/hooks/assistant/slack/events`. | All |
| **Email** | Email the assistant's address. Inbound mail arrives at `/api/hooks/assistant/email` and is authenticated. | All |
| **SMS** | Verify your phone number. Twilio inbound at `/api/hooks/assistant/sms`. | Pro (`assistant_sms`) |
| **Webhook** | Per-user URL at `/api/hooks/assistant/{token}` | All |

## Behavior across channels

- All channels share one memory and one session history. Origin is recorded for each session.
- Approvals can be answered in Slack with buttons.
- Slack, email and SMS inbound are signature checked and rate limited (`assistant-hooks`).

## Self-hosting

Configure `ASSISTANT_SLACK_*`, `ASSISTANT_INBOUND_DOMAIN`, `ASSISTANT_EMAIL*`, `TWILIO_*` and `ASSISTANT_SMS_*` variables. See [Environment variables](../13-self-hosting/environment-variables.md).
