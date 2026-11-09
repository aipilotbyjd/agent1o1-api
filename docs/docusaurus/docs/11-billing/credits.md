---
title: Credits, packs and overage
description: How credits are spent, reserved, topped up and capped.
sidebar_position: 2
---

# Credits, packs and overage

## What uses credits

- A base cost per workflow run.
- AI nodes, agent steps, embeddings and speech, by tokens and the model's price.
- Some integration nodes with fixed costs.

Every spend is an append-only **credit transaction** in the ledger, with the source (node run, agent step), amount and reason. Usage is rolled up per billing period.

## Credit sources

| Source | Notes |
|---|---|
| Plan allowance | Monthly credits, reset each period. Pro and Business can roll over 10%. |
| Trial credits | Granted when a trial starts |
| Credit packs | One-off purchases. They do not expire with the period. |
| Free credit claim | One-time claim for eligible new workspaces |
| Referral rewards | Credits earned from referrals |
| Admin grants | Manual grants from the Agent1o1 team |

Credits are consumed in grant order (expiring first).

## Reservations

Before a long run or agent turn the platform **reserves** an estimate, then settles the real cost. This prevents spending credits you do not have.

## Insufficient credits

When credits run out the API returns `InsufficientCredits`. Runs stop cleanly, and the workspace owner is notified.

## Overage

With the `credit_overage` feature you can allow spending beyond your balance, billed at the end of the period (or when a threshold is hit) through an invoice. Failed overage invoices are retried and recorded (`OverageInvoiceAttempt`).

Configure at **Billing → Overage** (`GET/PUT /billing/overage`).

## Credit packs

`GET /billing/credit-packs` lists packs. `POST /billing/credit-packs/checkout` buys one through Stripe.

## Usage insights

`GET /billing/credits` and `/usage-insights` show balance, burn rate, top workflows and agents, and per-model spend.

## Spend limits

Plans cap **max credits per run**, and AI cost per **day and week** in USD. A platform kill switch can pause spend globally in an emergency.
