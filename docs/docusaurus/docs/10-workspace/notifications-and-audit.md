---
title: Notifications and audit log
description: Choose how you are alerted, route alerts to Slack or email, and review who did what.
sidebar_position: 3
---

# Notifications and audit log

## Personal preferences

**Settings → Notifications** lets you choose, per event, whether you get in-app, email or channel notifications. Events include run failed, approval requested, credits low, trigger disabled, publish request, and more.

`GET/PUT /notification-preferences`.

## Notification channels

Workspace admins add channels such as email or Slack webhooks under **Settings → Notification channels**. Each has a **Test** action. Permission: `notification-channel.manage`.

## In-app notifications and announcements

The bell shows notifications. Platform **announcements** (product news, maintenance) appear in the app and can be dismissed.

## Credit alerts

Credit thresholds (for example 75% and 90%) send alerts. Configure at **Billing → Credit notifications**.

## Audit log

`GET /workspaces/{workspace}/audit-logs` (permission `audit-log.view`) lists workspace actions: who, what, when, from where. Auth events (sign-in, 2FA, token revocation) are tracked separately per user.
