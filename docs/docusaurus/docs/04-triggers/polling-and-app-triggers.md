---
title: Polling and app triggers
description: Start workflows from events in connected apps, by push where possible and polling otherwise.
sidebar_position: 4
---

# Polling and app triggers

App triggers use your [connection](../07-connections/connectors.md) to watch an app.

## Push vs poll

| Mode | How it works | Latency |
|---|---|---|
| **Push** | The app calls Agent1o1 at `/api/hooks/apps/{provider}` (a "watch" is registered for you). Signatures are verified per provider. | Seconds |
| **Poll** | Agent1o1 asks the app on an interval and diffs the result. State is kept in a cursor. | Interval (1–15 min by plan) |

The node tells you which mode applies. Examples:

- Telegram, Typeform, Mailchimp, Kit, Stripe, Shopify: push.
- Discord messages, new Jira or Linear issues: poll.
- Twilio: push for a named number, otherwise poll.

## Watches

For push apps a **watch** (`ProviderWatch`) is registered with the provider and renewed automatically (`triggers:renew-watches` every 10 minutes). A daily check verifies the watch still points to us. If someone replaces it, the trigger shows **reconnect needed**.

## Never take over what is in use

If a provider only allows one webhook and it already points elsewhere, we do not replace it. The trigger is refused with a sentence naming where it points, and poll-based triggers keep polling.

## Enabling push per provider (self-hosting)

Push is enabled with `TRIGGERS_PUSH_<PROVIDER>_ENABLED` environment variables, plus provider secrets such as signing secrets and Pub/Sub topics. See [Environment variables](../13-self-hosting/environment-variables.md).

## Presets

`GET /catalog/trigger-presets` returns ready-made triggers grouped by app, for example "New row in Google Sheets" or "New Stripe payment".
