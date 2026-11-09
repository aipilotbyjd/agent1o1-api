---
title: Workspaces and members
description: Create and switch workspaces, invite people, manage membership.
sidebar_position: 1
---

# Workspaces and members

## Workspaces

- One is created at sign-up. Create more from the workspace picker.
- Switch with the picker (`POST /user/switch-workspace`). The workspace in the URL decides which data you see.
- Rename or delete under **Settings → Workspace**. Deleting needs `workspace.delete` (owner).
- Each workspace has its own plan, credits, billing and Stripe customer.

## Members and invitations

1. **Settings → Members → Invite**. Enter an email and a role.
2. The invitee gets an email. They accept at `/invitations/{id}/accept` after signing in or signing up.
3. Admins can change a member's role or remove them. Members can leave.

Limits: Free allows 2 members, Starter 5, Pro and Business unlimited.

## Suspended workspaces

If a workspace is suspended (non-payment or abuse), actions return `WorkspaceSuspended` and runs stop until it is reinstated.

## Workspace context

Every request is checked for workspace membership and role before it runs.
