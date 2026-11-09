---
title: Run a playbook from code
description: Start a run, check its progress and read the result.
sidebar_position: 2
---

# Run a playbook from code

Base address: `https://YOUR-API-HOST/api/public/v1`. Send `Authorization: Bearer YOUR_API_KEY` on every call.

## 1. Find the playbook

```bash
curl .../workflows -H "Authorization: Bearer $KEY"
```

Use the playbook's `id` from the list. To see what inputs it needs:

```bash
curl .../workflows/WORKFLOW_ID/interface -H "Authorization: Bearer $KEY"
```

## 2. Start a run

Needs `workflows:write`.

```bash
curl -X POST .../workflows/WORKFLOW_ID/runs \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"inputs": {"text": "Summarise this"}}'
```

The response includes the run's `id`.

## 3. Check progress

Needs `runs:read`.

```bash
curl .../runs/RUN_ID -H "Authorization: Bearer $KEY"
```

Repeat until `status` is `completed`, `failed` or `cancelled`. Wait a few seconds between checks.

To see each step: `GET /runs/RUN_ID/node-runs`.

## Cancel or retry

- `POST /runs/RUN_ID/cancel`
- `POST /runs/RUN_ID/retry`

## All playbook and run endpoints

| Method | Path | Permission |
|---|---|---|
| GET | `/workflows` | `workflows:read` |
| GET | `/workflows/{id}` | `workflows:read` |
| GET | `/workflows/{id}/interface` | `workflows:read` |
| GET | `/workflows/{id}/graph` | `workflows:read` |
| POST | `/workflows` | `workflows:write` |
| PATCH | `/workflows/{id}` | `workflows:write` |
| DELETE | `/workflows/{id}` | `workflows:write` |
| PUT | `/workflows/{id}/graph` | `workflows:write` |
| POST | `/workflows/{id}/graph/validate` | `workflows:write` |
| POST | `/workflows/{id}/versions` | `workflows:write` (publish) |
| POST | `/workflows/{id}/runs` | `workflows:write` |
| POST | `/runs/{id}/cancel`, `/runs/{id}/retry` | `workflows:write` |
| GET | `/runs`, `/runs/{id}`, `/runs/{id}/node-runs` | `runs:read` |

For a run that waits on an outside system, see [Webhooks](./webhooks.md).
