---
title: FAQ and troubleshooting
description: Common questions and fixes.
sidebar_position: 3
---

# FAQ and troubleshooting

## General

**What is the difference between a workflow and an agent?**
A workflow follows the steps you drew. An agent decides its own steps using tools. Combine them by giving an agent a workflow as a tool, or by dropping an Agent node into a workflow.

**What is the difference between an agent and the assistant?**
Agents are shared, workspace-level and configured by builders. The assistant is personal to one user, with its own memory, inbox and channels.

**Where do my files go?**
They are artifacts in your workspace storage.

## Running

**My run is stuck on `awaiting_callback`.**
A Wait node is waiting for a call to its callback URL. Send the callback, set a timeout, or cancel the run.

**My run is stuck on `awaiting_approval`.**
Open **Approvals** and decide, or cancel the run.

**My workflow will not publish.**
Read the validation errors. Usually a required setting is empty, a port type does not match, or a condition is invalid.

**A node fails with "missing secret".**
Create the secret with that exact name, or update the reference.

## Triggers

**My webhook returns 429.**
You are over the rate limit. Retry with backoff.

**An app trigger says reconnect needed.**
The token or webhook was changed. Reconnect the app.

**Events show as `ignored`.**
They did not match the trigger's conditions.

## Billing

**Why did my run stop with insufficient credits?**
The balance reached zero. Buy a pack, upgrade, or enable overage.

**Why can't I pick a model?**
It is not on your plan, or it needs your own provider key.

## API

**I get 403 with a valid key.**
The key lacks the needed ability. Create a key with the right abilities.

**Agent messages time out.**
Agent turns can be long. Use a client timeout of at least 120 seconds.
