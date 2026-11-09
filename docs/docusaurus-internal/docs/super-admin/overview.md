---
title: Super admin console
description: The platform back-office for staff — accounts, billing, catalog, health and audit.
sidebar_position: 1
---

# Super admin console

A separate API and console for platform staff. It has its own login, tokens, rate limits and audit trail, and is **disabled unless** `super_admin.enabled` is true. Its URL prefix is configurable (`super_admin.prefix`), so change the default in production.

## Roles

| Role | Typical use |
|---|---|
| Owner | Everything |
| Operator | Operate the platform, pause providers, manage catalog |
| Finance | Billing, pricing, revenue |
| Support | Look up accounts, notes, tags |
| Viewer | Read only |

Each role grants a set of abilities (`SuperAdminAbility`).

## Areas

| Area | What you can do |
|---|---|
| **Accounts, users, workspaces, customers** | Search, inspect, add notes and tags, suspend and reinstate |
| **Runs** | Inspect platform-wide runs |
| **Billing and pricing** | Plans, price history, coupons, credit packs, grants, billing settings, spend kill switch |
| **Revenue** | Daily revenue snapshots and reports |
| **Models** | Model catalog, prices and verification, provider pauses, routes |
| **Catalog** | Node categories, apps, node overrides, templates |
| **Referrals** | Programs, reward rules, blocked domains, approvals |
| **Broadcasts** | Email campaigns and in-app announcements to segments |
| **System** | Health snapshots, scheduled command runs, setup data status |
| **Alerts** | Alert rules, events, daily digest |
| **Intelligence** | Experiments, predictions, recommendations |
| **Audit log** | Tamper-evident log of every admin action (verified daily) |

## Security

- Separate authentication with its own rate limits (`super-admin-auth`, `super-admin`, `super-admin-sensitive`).
- Sensitive actions are rate limited more tightly and logged.
- Admin edits to plans, referral programs and prices are tracked, and syncs never overwrite them.
- Create the first owner with `php artisan super-admin:create`.
