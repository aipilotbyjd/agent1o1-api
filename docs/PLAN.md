# agent1o1 — Coolify Zero-Downtime Deployment Plan

One repo. Four Coolify resources built from four Dockerfiles. Managed Redis. Traefik terminates TLS.

| Resource  | Dockerfile           | Process                  | Port | Health check          |
|-----------|----------------------|--------------------------|------|-----------------------|
| web       | Dockerfile.web       | nginx + php-fpm          | 8080 | GET /up (Laravel)     |
| horizon   | Dockerfile.horizon   | artisan horizon          | —    | healthcheck-horizon   |
| reverb    | Dockerfile.reverb    | artisan reverb:start     | 8000 | GET /up (Reverb)      |
| scheduler | Dockerfile.scheduler | artisan schedule:work    | —    | healthcheck-schedule  |

---

## 1. How zero-downtime works, per resource

### Web — Coolify rolling update

Deploy flow: Coolify builds the new image, starts the new container alongside the old one, waits for the health check to pass, points Traefik at the new container, then stops the old one. Traffic never hits a dead container.

Three conditions must hold or Coolify silently falls back to stop-then-start:

1. **Health check enabled and passing** — set path `/up`, port `8080` in the resource's Healthcheck settings.
2. **Default container naming** — do not set a custom container name.
3. **No host port mappings** — leave "Ports Mappings" empty; Traefik-only routing. Publishing a host port kills the rolling machinery.

Built-in fail-safe: AUTORUN runs migrations + `artisan optimize` during boot. If a migration fails, the boot script exits non-zero, the container never goes healthy, and Coolify keeps the old container serving. A broken deploy costs you nothing.

Set the health check **start period / initial delay to ~40–60s** so migrations and cache warming can finish before checks count against you.

### Reverb — the WebSocket problem

During a rolling swap two Reverb instances exist for a short window. Two things must be true:

1. **Broadcasts during the overlap must reach clients on both containers.** Set `REVERB_SCALING_ENABLED=true`. Reverb instances then relay events to each other through Redis pub/sub, so an event fired while the old container still holds connections gets delivered everywhere.
2. **Dropped sockets must recover.** When the old container stops, its connections die — Laravel Echo reconnects automatically and lands on the new container. Users see a sub-second blip at worst.

Health check: Reverb serves plain HTTP `GET /up` → `{"health":"OK"}` on its own port. Point Coolify's health check at `/up:8000`. This is what ends the Traefik-deregistration saga — an HTTP check the WebSocket server actually answers.

### Horizon — graceful drain

Two Horizons overlapping is *safe*: both consume the same Redis queues, jobs are just split between them. The only rule is drain, don't kill:

- SIGTERM makes Horizon stop accepting jobs and finish in-flight ones.
- Set the resource's **stop timeout (Graceful Shutdown) to 60–120s** — must exceed your longest job timeout. Docker's default 10s will hard-kill mid-job.

### Scheduler — prevent double-fire

Risk: old + new scheduler both alive across a minute boundary → tasks run twice. Fix in code, once:

```php
Schedule::command('app:whatever')
    ->everyFiveMinutes()
    ->onOneServer();   // Redis cache lock; add to EVERY task
```

`onOneServer()` requires a shared cache — your Redis qualifies. With that, overlap is harmless and you don't care about deploy ordering for this container.

---

## 2. Migration discipline (the part infra can't solve)

During every rollout there's a window where **old code runs against new schema**. Rules:

- **Additive first.** New columns nullable or with defaults. Deploy, backfill, then tighten.
- **Never rename in one deploy.** Add new column → dual-write → switch reads → drop old column in a *later* deploy.
- **Destructive changes ship alone**, after no running code references the old schema.
- `AUTORUN_LARAVEL_MIGRATION_ISOLATION=true` → runs `migrate --isolated` with a Redis lock, so only one container ever migrates even if several boot at once.

Rollback story: redeploy the previous commit. Because migrations are additive, the old code still runs fine against the newer schema — no down-migrations in prod.

---

## 3. serversideup/php features this plan uses

- **AUTORUN** — on boot: `storage:link`, `migrate`, and `artisan optimize` (config/route/view/event caches; Laravel ≥ 11.38 uses `optimize --except` for anything you toggle off). Debug a weird boot with `AUTORUN_DEBUG=true`.
- **Baked health commands** — `healthcheck-horizon` (wraps `horizon:status`), `healthcheck-reverb`, `healthcheck-schedule` (pgrep) live in `/usr/local/bin`.
- **`HEALTHCHECK_PATH=/up`** — the image's own Docker healthcheck then validates Laravel booting, not just nginx being up.
- **PHP tuning via env, no ini files** — `PHP_MEMORY_LIMIT`, `PHP_POST_MAX_SIZE`, `PHP_UPLOAD_MAX_FILE_SIZE`, `PHP_MAX_EXECUTION_TIME`, `PHP_OPCACHE_ENABLE`, FPM pool sizing.
- **Unprivileged by default** — runs as `www-data` on 8080, no root-user Dockerfile gymnastics.
- **Boot hooks** — drop a script in `/etc/entrypoint.d/` if you ever need custom pre-start logic.
- **Pin the base image** — use a versioned tag (format like `serversideup/php:8.4-fpm-nginx-v3.x.y`, check Docker Hub for current) once stable, so a base-image update never surprises a rebuild.

---

## 4. Environment variables

Coolify does **not** share env between resources — copy the shared set into all four.

**Shared (all 4 resources):**
```
APP_KEY, APP_ENV=production, APP_DEBUG=false
DB_*  REDIS_*
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET
```

**Web only:**
```
AUTORUN_ENABLED=true
AUTORUN_LARAVEL_MIGRATION_ISOLATION=true
```

**Horizon / Reverb / Scheduler:**
```
AUTORUN_ENABLED=false
```

**Reverb resource only:**
```
REVERB_SCALING_ENABLED=true
# server side (the daemon itself — CLI flags in the Dockerfile also set these):
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8000
```

**Web + anything that renders Echo config (client-facing values):**
```
REVERB_HOST=ws.agent1o1.com
REVERB_PORT=443
REVERB_SCHEME=https
```

---

## 5. Coolify settings checklist (per resource)

| Setting             | web              | horizon   | reverb            | scheduler |
|---------------------|------------------|-----------|-------------------|-----------|
| Build pack          | Dockerfile       | Dockerfile| Dockerfile        | Dockerfile|
| Dockerfile location | /Dockerfile.web  | /Dockerfile.horizon | /Dockerfile.reverb | /Dockerfile.scheduler |
| Domain              | api.agent1o1.com | —         | ws.agent1o1.com   | —         |
| Exposed port        | 8080             | —         | 8000              | —         |
| Coolify healthcheck | /up : 8080       | (Dockerfile HEALTHCHECK covers it) | /up : 8000 | (Dockerfile HEALTHCHECK covers it) |
| Stop timeout        | default          | 60–120s   | default           | default   |
| Container name      | default          | default   | default           | default   |
| Port mappings       | empty            | empty     | empty             | empty     |

Rolling updates only really matter on **web** and **reverb** (they hold live traffic). Horizon and scheduler restarting with a brief gap/overlap is by design — drain + `onOneServer()` make it safe.

---

## 6. Deploy flow

1. `git push` → all four resources rebuild. Layers are identical until the last lines, so builds 2–4 are cache hits.
2. Web boots, takes the Redis migration lock, migrates, warms caches, goes healthy, Traefik flips.
3. Workers boot with new code. A worker may briefly run new code before migrations land — backward-compatible migrations (section 2) make that a non-event. If you ever need strict ordering, chain deploys with Coolify's deploy webhooks (web first, then trigger the other three) — don't bother until it actually bites.

## 7. One more zero-downtime prerequisite

No state on container disk. Sessions/cache already live in Redis; any **user uploads must go to object storage (R2/S3)**, not `storage/app` — rolling containers means local files evaporate on every deploy. If you're already API-only + R2, you're done.
