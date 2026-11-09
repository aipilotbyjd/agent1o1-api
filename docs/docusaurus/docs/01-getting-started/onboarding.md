---
title: Account and onboarding
description: Signing up, signing in, two-factor authentication, and the first-run onboarding flow.
sidebar_position: 3
---

# Account and onboarding

## Signing up and signing in

| Method | Notes |
|---|---|
| Email and password | Email verification is required. Password rules are enforced on sign-up and reset. |
| Google, GitHub | Social login through OAuth. Accounts with the same verified email are linked. |
| Magic link | Passwordless sign-in link sent by email. |
| Invitation | Accept a workspace invitation link, then sign in or sign up. |

Auth endpoints are rate limited (10 requests per minute per IP).

## Two-factor authentication

Under **Settings → Security** you can:

1. Enable 2FA and scan the QR code with an authenticator app.
2. Confirm with a code to finish setup.
3. Save your **recovery codes**. You can regenerate them at any time.

When 2FA is on, sign-in returns a challenge and you complete it with a code or a recovery code.

## Sessions

**Settings → Security** lists your signed-in sessions with the device and last activity. Revoke one, or use **Log out everywhere**. A password reset revokes all sessions automatically.

## Account locked and session expired

Repeated failed sign-ins temporarily lock the account and show an **Account locked** page. An expired session returns you to sign-in and brings you back to where you were.

## Onboarding

New users answer a few questions: **job role** and **how they discovered us**, then pick a starting point (a template or a blank workflow). Onboarding progress is saved per user, so you can leave and resume.

## Changing email, password and profile

- Change password: requires the current password and can sign out other sessions.
- Change email: a confirmation link goes to the new address. The old address stays active until confirmed. Cancel a pending change from your profile.
- Profile: name, avatar, timezone, language.
