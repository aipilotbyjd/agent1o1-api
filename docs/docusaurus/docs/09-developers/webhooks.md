---
title: Webhooks
description: Start playbooks from your own systems, and resume paused runs.
sidebar_position: 4
---

# Webhooks

A webhook is a web address that starts something when you send it a request.

## Start a playbook

Add a **Webhook trigger** to a playbook and copy its address from **Settings → Triggers**.

```bash
curl -X POST https://YOUR-API-HOST/api/hooks/YOUR_TOKEN \
  -H "Content-Type: application/json" \
  -d '{"customer": "Acme", "amount": 42}'
```

The request's body, query string and headers become available to the steps in your playbook.

- The long code in the address is the password. Keep it secret.
- If it leaks, **rotate** it in **Settings → Triggers**. The old address stops working at once.
- You'll get a quick reply that the event was received. The playbook then runs in the background.

## Resume a waiting playbook

A **Wait** step pauses until another system calls back. Send the request to the callback address shown for that run:

```bash
curl -X POST https://YOUR-API-HOST/api/hooks/wait/CALLBACK_TOKEN \
  -H "Content-Type: application/json" -d '{"approved": true}'
```

The body you send becomes the step's result.

## Limits

Webhook addresses are rate limited. If you go over, you'll get `429`. Wait and retry. Events are saved on arrival, so a short delay on our side doesn't lose them.
