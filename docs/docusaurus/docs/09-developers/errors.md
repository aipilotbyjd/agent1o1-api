---
title: Errors and limits
description: What the API's error responses mean and how to respond.
sidebar_position: 5
---

# Errors and limits

## Status codes

| Code | Meaning | What to do |
|---|---|---|
| 200, 201 | Success | |
| 401 | Missing or invalid API key | Check the `Authorization` header and that the key wasn't revoked |
| 402 | Out of credits | Add credits or upgrade |
| 403 | The key isn't allowed to do this, or the plan doesn't include it | Use a key with the right permission, or upgrade |
| 404 | Not found, or not in this workspace | Check the id |
| 409 | Conflict, such as a playbook that was changed while you edited it | Fetch the latest and try again |
| 422 | Something in the request is invalid | Read the `errors` in the response |
| 429 | Too many requests | Wait for the time in `Retry-After`, then retry |
| 5xx | Something went wrong on our side | Retry with a growing delay |

## Common messages

| Message | Meaning |
|---|---|
| Insufficient credits | Your balance is too low |
| Plan limit reached | You've hit a limit on your plan, such as number of playbooks |
| Feature not available | Your plan doesn't include this |
| Model not on your plan | Pick another model, or upgrade |
| Your own key is required | This model needs your own AI provider key |
| Usage limit reached | A daily or weekly AI spending limit was reached |
| Run credit limit reached | One run used the maximum allowed credits |
| Workspace suspended | Resolve billing, or contact support |
| Missing secret | A step uses a Vault secret that doesn't exist |
| Playbook is invalid | The response lists each problem |
| Approval required to publish | Your workspace needs review before publishing |

## Retrying safely

Retry `429` and `5xx` errors after waiting, with a longer wait each time. Don't retry other `4xx` errors without changing the request.

## Rate limit

Each API key can make 60 requests per minute.
