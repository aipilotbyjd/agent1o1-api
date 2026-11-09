---
title: Errors and limits
description: HTTP status codes, the typed error conditions and the rate limiters you may meet.
sidebar_position: 6
---

# Errors and limits

Domain failures raise typed exceptions that are mapped centrally to HTTP responses, so both APIs return the same shape.

## Status codes

| Code | Meaning |
|---|---|
| `200`, `201`, `204` | Success |
| `401` | Missing or invalid credentials |
| `402` | Payment or credits issue (for example insufficient credits) |
| `403` | Authenticated but not allowed (role, ability, plan feature) |
| `404` | Not found, or not in your workspace |
| `409` | Conflict (for example the workflow graph changed while editing) |
| `422` | Validation failed. Field errors in `errors` |
| `429` | Rate limited. See `Retry-After` |
| `5xx` | Server error. Retry with backoff |

## Typed conditions

| Condition | Typical cause |
|---|---|
| `InsufficientCredits` | Balance is too low |
| `PlanLimitExceeded` | Created more than the plan allows |
| `FeatureNotAvailable` | Feature is not on your plan |
| `ModelNotOnPlan` | Model is restricted to higher plans |
| `OwnAiKeyRequired` | The model needs your own provider key |
| `UsageBlocked`, `CostLimitReached` | Daily or weekly AI spend cap |
| `RunBudgetExceeded` | The run passed its credits-per-run cap |
| `WorkspaceSuspended` | The workspace is suspended |
| `MissingSecret` | A referenced secret does not exist |
| `ConnectorError` | The app rejected the call |
| `ProviderPaused`, `ModelBusy` | AI provider paused or overloaded |
| `WorkflowValidation` | Graph is invalid. Details list each problem |
| `WorkflowBuilderConflict` | The graph changed under the builder |
| `PublishApprovalRequired` | The workspace needs approval to publish |
| `WatchRefused` | The app's webhook slot is already in use |
| `InvalidNodeInput` | A node received bad input |

## Rate limiters

| Limiter | Limit |
|---|---|
| `auth` | 10 per minute per IP |
| `public-api` | 60 per minute per key |
| Webhook URLs | Rate limited per URL |
| `workflow-builder-messages` | 10 per minute |
| `workflow-builder-assist` | 20 per minute |
| `connector-tests` | 10 per minute |
| `ai-credential-checks` | 10 per minute |
| `model-trials` | 6 per minute |
| `page-reads` | 120 per minute |
| `page-submissions` | 10 per minute |
| `page-unlock` | 5 per minute |
| `referral-visits` | 30 per minute per IP |
| `referral-claims` | 5 per minute |

## Retrying safely

Retry `429` and `5xx` with exponential backoff and jitter. Do not retry `4xx` other than `429` without changing the request.
