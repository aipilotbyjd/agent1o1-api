---
title: Publish and versions
description: Publish a playbook, go back to an earlier version, and require review before publishing.
sidebar_position: 8
---

# Publish and versions

Editing and running are kept apart on purpose. You can keep improving a playbook without breaking one that is already running.

## Publish

Choose **Publish** in the editor. Agent1o1 checks the playbook first and lists anything that needs fixing, such as a required setting that is empty, a connection that doesn't fit, or a trigger condition that isn't valid. Once it passes, your changes go live as a new **version**.

## Versions

Every publish is saved as a version. Open the version history to see what changed, and **restore** an earlier version if a change caused a problem.

## Review before publishing

Admins can require review before anything goes live (Starter plan and above).

1. An editor chooses **Publish** and a request is created.
2. A reviewer **approves** or **rejects** it. The requester can withdraw it.
3. When approved, it goes live.

Pending requests appear on the **Approvals** page.

## Tips

- Test with **Run** before you publish.
- Use tags such as "live" or "experimental" so teammates know what is safe to touch.
