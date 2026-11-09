---
title: Smart Inbox
description: Automatic email classification, labeling and reply drafting.
sidebar_position: 3
---

# Smart Inbox

Smart Inbox connects to your mailbox and prepares it for you. Requires the `smart_inbox` plan feature.

## Enable

1. Connect Gmail or Outlook.
2. Turn on Smart Inbox and choose which labels to manage.
3. Choose draft mode (never, suggest, or draft automatically).

## What happens to each message

1. The inbox is checked every two minutes (`assistant:check-inboxes`).
2. A classification job (unique per provider message id) assigns a **label** from groups such as needs reply, FYI, newsletter.
3. If a reply is expected, a **draft** is created in your own mailbox in your learned writing style.
4. Nothing is sent automatically. You review drafts yourself.

## Labels

Labels are grouped (for example action, waiting, low priority). You can add, rename and remove them. Existing labels are never deleted from your mailbox without your action.

## Drafting rules

Drafts follow your style profile and tone settings, avoid committing you to dates and amounts you have not confirmed, and cite source messages.
