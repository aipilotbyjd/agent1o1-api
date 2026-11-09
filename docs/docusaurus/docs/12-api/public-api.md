---
title: Public API reference
description: Endpoints available to API keys — workflows, runs, agents and connectors.
sidebar_position: 3
---

# Public API reference

Base: `https://api.example.com/api/public/v1`. Auth: `Authorization: Bearer <api key>`. Rate limit: 60 per minute per key.

**Check:** request and response field names below were derived from route definitions. Confirm payloads against the controllers and resources before publishing.

## Account

| Method | Path | Notes |
|---|---|---|
| GET | `/me` | The workspace and key the request resolved to |

## Workflows

| Method | Path | Ability |
|---|---|---|
| GET | `/workflows` | `workflows:read` |
| GET | `/workflows/{workflow}` | `workflows:read` |
| GET | `/workflows/{workflow}/graph` | `workflows:read` |
| GET | `/workflows/{workflow}/interface` | `workflows:read` |
| POST | `/workflows` | `workflows:write` |
| PATCH | `/workflows/{workflow}` | `workflows:write` |
| DELETE | `/workflows/{workflow}` | `workflows:write` |
| PUT | `/workflows/{workflow}/graph` | `workflows:write` (replace graph) |
| POST | `/workflows/{workflow}/graph/validate` | `workflows:write` |
| POST | `/workflows/{workflow}/versions` | `workflows:write` (publish) |

## Runs

| Method | Path | Ability |
|---|---|---|
| POST | `/workflows/{workflow}/runs` | `workflows:write` |
| POST | `/runs/{run}/cancel` | `workflows:write` |
| POST | `/runs/{run}/retry` | `workflows:write` |
| GET | `/runs` | `runs:read` |
| GET | `/runs/{run}` | `runs:read` |
| GET | `/runs/{run}/node-runs` | `runs:read` |
| GET | `/runs/{run}/node-runs/{nodeRun}` | `runs:read` |

### Start a run

```bash
curl -X POST https://api.example.com/api/public/v1/workflows/WORKFLOW_ID/runs \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"inputs": {"text": "hello"}}'
```

Poll `GET /runs/{run}` until `status` is `completed`, `failed` or `cancelled`. See [statuses](../15-reference/statuses.md).

## Agents (`agents:invoke`)

| Method | Path |
|---|---|
| GET | `/agents` |
| GET | `/agents/{agent}` |
| POST | `/agents/{agent}/sessions` |
| GET | `/agents/{agent}/sessions/{session}` |
| POST | `/agents/{agent}/sessions/{session}/messages` |
| GET | `/agents/{agent}/sessions/{session}/actions` |
| POST | `/agents/{agent}/sessions/{session}/actions/decisions` |

Sending a message can take a while. The route allows a long agent turn, so use a generous client timeout (at least 120 seconds).

## Connectors (`connectors:manage`)

| Method | Path |
|---|---|
| GET | `/connectors` |
| GET | `/connector-credentials` |
| POST | `/connector-credentials` |
| GET | `/connector-credentials/{id}` |
| DELETE | `/connector-credentials/{id}` |

## Pagination and filtering

List endpoints are paginated. Use `page` and `per_page` (when supported) and follow `links.next`.
