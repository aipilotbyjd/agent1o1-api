---
title: Plans and limits
description: Free, Starter, Pro and Business plans, what each includes, and how limits are enforced.
sidebar_position: 1
---

# Plans and limits

:::note
Numbers below come from the seeded plan data (`database/setup/plans.php`). Admins can edit plans after the first sync, so confirm against your live catalog.
:::

| | Free | Starter | Pro | Business |
|---|---|---|---|---|
| Price (monthly) | $0 | $29 | $99 | $299 |
| Credits per month | 100 | 4,500 | 15,000 | 46,000 |
| Workflows | 3 | 25 | Unlimited | Unlimited |
| Agents | 1 | 10 | Unlimited | Unlimited |
| Members | 2 | 5 | Unlimited | Unlimited |
| Pages | 3 | 25 | Unlimited | Unlimited |
| Active triggers | 2 | 25 | Unlimited | Unlimited |
| Min polling interval | 15 min | 5 min | 1 min | 1 min |
| Concurrent runs | 1 | 3 | 10 | 25 |
| Max credits per run | 25 | 500 | 2,000 | 6,000 |
| AI spend cap (daily / weekly, USD) | 0.20 / 0.50 | 5 / 15 | 20 / 60 | 60 / 180 |
| Trial | – | 14 days, 1,000 credits | 2,000 credits | 2,000 credits |
| Credit rollover | – | – | 10% | 10% |

Billing intervals: monthly, quarterly, yearly, and lifetime offers on paid plans.

## Feature flags

| Feature | Starter | Pro |
|---|---|---|
| Git sync | ✓ | ✓ |
| Workflow approvals | ✓ | ✓ |
| Public pages | ✓ | ✓ |
| Credit packs | ✓ | ✓ |
| Credit overage | ✓ | ✓ |
| Custom nodes | – | ✓ |
| Priority support | – | ✓ |
| Remove branding | – | ✓ |
| Smart Inbox | – | ✓ |
| Assistant sandbox | – | ✓ |
| Assistant SMS | – | ✓ |

## How limits are enforced

A central `PlanLimitGate` checks workflows, agents, members, pages and active triggers on create. When a cap is reached, the API returns `PlanLimitExceeded`. Features not on your plan return `FeatureNotAvailable`.

## Subscribing, changing and cancelling

- **Checkout**: `POST /billing/subscription/checkout` opens Stripe Checkout.
- **Preview a change**: `GET /billing/subscription/preview` shows proration.
- **Cancel / resume**: cancels at period end, or undoes it.
- **Portal**: `POST /billing/portal` opens the Stripe customer portal for cards and invoices.
- **Invoices**: `GET /billing/invoices`, `/invoices/upcoming`.

Failed payments follow dunning rules and may suspend the workspace after the grace period.
