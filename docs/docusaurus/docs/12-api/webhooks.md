---
title: Webhooks
description: Inbound webhook endpoints, how each is authenticated, and what they are for.
sidebar_position: 5
---

# Webhooks

All inbound webhooks are public routes that authenticate themselves, and live outside the API-key and session middleware.

| Endpoint | Purpose | Authentication |
|---|---|---|
| `POST/GET /api/hooks/{token}` | Workflow or agent webhook trigger | Token in URL (optional signature) |
| `POST/GET /api/hooks/apps/{provider}` | App push events (Telegram, Discord, Twilio, Typeform, ...) | Provider signature |
| `POST /api/hooks/wait/{token}` | Resume a run paused on a Wait node | Token in URL |
| `POST /api/hooks/assistant/{token}` | Personal assistant webhook | Token in URL |
| `POST /api/hooks/assistant/email` | Inbound email to the assistant | Shared token and sender checks |
| `POST /api/hooks/assistant/sms` | Inbound SMS | Twilio signature |
| `POST /api/hooks/assistant/slack/events` | Slack events | Slack signing secret |
| `GET /api/hooks/assistant/slack/oauth` | Slack install redirect | OAuth state |
| `POST /api/slack/agent-actions` | Approve or reject agent actions from Slack | Slack signing secret |
| `POST /api/stripe/webhook` | Stripe billing events | Stripe signature |

## Provider-specific verification

- **Telegram**: per-bot secret in `X-Telegram-Bot-Api-Secret-Token`.
- **Discord**: Ed25519 signature over timestamp and body, answered within 3 seconds.
- **Twilio**: HMAC-SHA1 of URL and sorted fields, account SID must match.
- **Stripe**: signing secret. Every event is recorded once (`ProcessedWebhookEvent`) so retries are safe, and failures are stored for replay.

## Delivery guarantees

Trigger events are stored before processing and retried if stuck. Return `2xx` quickly. Do not rely on ordering.

## Rate limits

`trigger-hooks` and `assistant-hooks` limiters apply per URL or IP. Over the limit returns `429`.
