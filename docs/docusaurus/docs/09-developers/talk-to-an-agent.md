---
title: Talk to an agent from code
description: Start a conversation with an agent, send messages, and answer approval requests.
sidebar_position: 3
---

# Talk to an agent from code

Needs a key with `agents:invoke`.

## 1. List your agents

```bash
curl .../agents -H "Authorization: Bearer $KEY"
```

## 2. Start a conversation

```bash
curl -X POST .../agents/AGENT_ID/sessions -H "Authorization: Bearer $KEY"
```

The response includes the conversation's `id`.

## 3. Send a message

```bash
curl -X POST .../agents/AGENT_ID/sessions/SESSION_ID/messages \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"message": "What is your refund policy?"}'
```

Agents can take a while to answer, especially when they use several tools. Allow **at least 2 minutes** before your request times out.

## 4. Handle approval requests

If the agent needs permission, list what is waiting and decide:

```bash
curl .../agents/AGENT_ID/sessions/SESSION_ID/actions -H "Authorization: Bearer $KEY"
curl -X POST .../agents/AGENT_ID/sessions/SESSION_ID/actions/decisions \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"decisions": [{"action": "ACTION_ID", "approve": true}]}'
```

## Endpoints

| Method | Path |
|---|---|
| GET | `/agents`, `/agents/{id}` |
| POST | `/agents/{id}/sessions` |
| GET | `/agents/{id}/sessions/{session}` |
| POST | `/agents/{id}/sessions/{session}/messages` |
| GET | `/agents/{id}/sessions/{session}/actions` |
| POST | `/agents/{id}/sessions/{session}/actions/decisions` |
