---
title: Deployment and operations
description: Production checklist, deploy steps, backups and the Stripe webhook.
sidebar_position: 5
---

# Deployment and operations

## Deploy steps

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan setup:catalog:status
php artisan setup:catalog:sync --dry-run   # review, then run without --dry-run
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan horizon:terminate              # supervisor restarts workers on new code
```

Build the frontend (`yarn build`) and serve `build/` as static files. The web app is deployed on Netlify; `netlify.toml` sends all routes to `index.html` for client-side routing.

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY` (back it up).
- PostgreSQL with backups and point-in-time recovery.
- Redis with persistence enabled for queues.
- S3-compatible storage for artifacts.
- Horizon, scheduler and Reverb running under a process manager.
- HTTPS everywhere. Cookies secure. CORS restricted to your frontend.
- Stripe live keys, and the webhook endpoint `https://api.example.com/api/stripe/webhook` registered.
- Provider OAuth apps created with the production redirect URLs.
- Mail provider verified (SPF, DKIM).
- Review plan limits and AI spend caps.
- Super admin created and its prefix changed from the default.
- `setup:catalog:status` is clean.

The internal `docs/LAUNCH_CHECKLIST.md` has the full list.

## Laravel Cloud

The API can be deployed on Laravel Cloud. Use separate resources for web, Horizon, scheduler and Reverb.

## Backups and key rotation

- Back up the database and `.env`.
- Rotating `APP_KEY` invalidates sessions and encrypted data unless you list the old key in `APP_PREVIOUS_KEYS`.
- Replacing the Passport keys signs everyone out.

## Troubleshooting

| Symptom | Check |
|---|---|
| Runs stay `pending` | Horizon running? Queue connection? `workflows-dispatch` supervisor |
| No nodes in the editor | `setup:catalog:status` and run the sync |
| Login fails with `invalid_client` | Passport client id and secret in `.env`, then `config:clear` |
| Realtime does not update | Reverb process, `REVERB_*` and `VITE_REVERB_*` match |
| Triggers not firing | Scheduler running? Trigger activated? Check events list |
| Stripe events missing | Webhook secret and endpoint, failures table |
