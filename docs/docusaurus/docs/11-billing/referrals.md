---
title: Referrals
description: Invite others and earn rewards.
sidebar_position: 3
---

# Referrals

Every user has a **referral code** and link.

## Flow

1. Share your link. A click records a **visit** (rate limited by IP).
2. The new user signs up and **claims** the referral.
3. When the activation event happens (for example the referred workspace subscribes or makes a payment), a **reward** is created.
4. Rewards are approved automatically or manually, then granted (credits, a discount or both).

## Rules

- Self-referrals and blocked email domains do not count.
- If the referred user is already on a plan, behavior is set by the program (skip or upgrade).
- Refunds in the qualifying period reverse the reward.
- Programs, reward rules, recipient (referrer, referee or both) and approval mode are managed by admins.

## Endpoints

`GET /referrals/me`, `PATCH /referrals/me`, `/referrals/program`, `/referrals/stats`, `/referrals/rewards`, plus `POST /referrals/visit` and `/referrals/claim`.
