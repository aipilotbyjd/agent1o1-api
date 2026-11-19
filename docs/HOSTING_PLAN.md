# Hosting Plan: Two Servers

Starting out on one VPS? Use `docs/HOSTING_PLAN_SINGLE_SERVER.md` first, and move to this plan when its "When to grow" triggers are reached.

How Agent1o1 (this API, `agent1o1-admin` and the `agent1o1` React frontend) is hosted in production: two Hostinger VPS servers managed by Coolify, with Cloudflare in front, builds in GitHub Actions and backups off-server. Work through the phases in order. Pair this with `docs/LAUNCH_CHECKLIST.md` (what must be true before launch) and `docs/BACKUP_RESTORE_RUNBOOK.md` (how to back up and restore).

Menu names in Coolify, Cloudflare and Hostinger change between versions. The steps describe the intent, so adjust the clicks if the UI differs.

Domains used below: `app.`, `api.`, `ws.`, `admin.`, `errors.`, `staging-api.` and `staging-ws.` on your domain.

---

## 1. Architecture

```
                 Users
                   │
            Cloudflare (DNS, SSL, WAF, DDoS, rate limits)
     ┌─────────────┼──────────────────────────────┐
     │             │ only Cloudflare IPs allowed  │
app.domain   api. / ws. / admin.               errors.
  Netlify          │                              │
                   ▼                              ▼
┌─── SERVER A · PRODUCTION · Hostinger KVM 4 (4 vCPU / 16 GB) ───┐
│ Runs only the live app and its data: no dashboard, no builds.  │
│  api-web        Dockerfile.web        :8080  → api.domain      │
│  api-reverb     Dockerfile.reverb     :8000  → ws.domain       │
│  api-horizon    Dockerfile.horizon    (no domain)              │
│  api-scheduler  Dockerfile.scheduler  (no domain, 1 replica)   │
│  admin          agent1o1-admin/Dockerfile :8080 → admin.domain │
│  Postgres 17 + pgvector  (private, WAL archiving → R2)         │
│  redis-queue  (AOF, noeviction)   redis-cache (LRU, no disk)   │
└───────────────────────────▲────────────────────────────────────┘
                            │ Tailscale private network (SSH, Coolify)
┌─── SERVER B · OPERATIONS · Hostinger KVM 2 (8 GB) ─────────────┐
│  Coolify control plane  (reachable only over Tailscale)        │
│  GlitchTip  → errors.domain     Uptime Kuma (watches A)        │
│  Staging environment (smaller copy of api + admin)             │
└────────────────────────────────────────────────────────────────┘

Off-server:
  GitHub Actions → tests → build images → GHCR → tells Coolify to deploy
  Cloudflare R2   → uploaded files + database backups
  AWS S3 (separate account, Object Lock) → immutable spatie backups
  UptimeRobot (free) → watches A and B from outside
  Password manager → APP_KEY, Passport keys, every env var
```

Why it is split this way:

- **The production server does one job.** A Coolify bug, a bad update or a Coolify vulnerability does not take down live traffic.
- **The Coolify dashboard is never public.** It holds root SSH access to production, so it is reachable only over Tailscale.
- **Builds happen in GitHub Actions,** never on the production CPU.
- **Monitoring runs on a different machine** from the one it watches, and UptimeRobot watches both from outside.

---

## 2. What to buy and sign up for

| Item | Choice | Purpose |
| --- | --- | --- |
| Server A | Hostinger **KVM 4**, Ubuntu 24.04 | Production |
| Server B | Hostinger **KVM 2**, Ubuntu 24.04, same region | Coolify, GlitchTip, Uptime Kuma, staging |
| Private network | **Tailscale** (free) | SSH and the Coolify dashboard |
| DNS and edge | **Cloudflare** (free) | Proxy, WAF, origin certificate, Access |
| Object storage | **Cloudflare R2** | Buckets `agent1o1-files` (versioning on), `agent1o1-files-staging`, `agent1o1-backups` |
| Immutable backups | **AWS S3 in a separate account**, Object Lock in compliance mode | `spatie/laravel-backup` (runbook §2.6) |
| Email | **Resend** or **Postmark** | Transactional mail. SPF, DKIM and DMARC on your domain |
| Frontend hosting | **Netlify** (already configured) | React app |
| Container registry | **GHCR** | Built images |
| External uptime | **UptimeRobot** (free) | Watches A and B |
| Secrets | **Bitwarden** or **1Password** | Offline copy of every secret |

---

## 3. Phase 0: Accounts and decisions (1 hour)

- [ ] Order both servers in the region closest to your users.
- [ ] Move the domain's DNS to Cloudflare.
- [ ] Create the R2 buckets and an R2 API token limited to those buckets.
- [ ] Create the second AWS account and an Object Lock bucket (Object Lock must be enabled at creation) with 30 to 90 day compliance retention.
- [ ] In GitHub, in both the API and admin repos, create environments `staging` and `production`, with yourself as a **required reviewer** on `production`.
- [ ] Create a password manager vault `agent1o1-production`.

---

## 4. Phase 1: Servers and network (half a day)

### 4.1 Harden both servers

```bash
# as root, once
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/ && chown -R deploy: /home/deploy/.ssh
apt update && apt -y upgrade && apt -y install ufw fail2ban unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
timedatectl set-timezone UTC
fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

In `/etc/ssh/sshd_config` set `PasswordAuthentication no` and `PermitRootLogin prohibit-password` (Coolify needs root SSH with a key), then `systemctl restart ssh`.

### 4.2 Tailscale on both servers and your laptop

```bash
curl -fsSL https://tailscale.com/install.sh | sh && tailscale up
```

Note each server's Tailscale IP (`100.x.y.z`).

### 4.3 Firewalls

Server A: web traffic only from Cloudflare, SSH only over Tailscale.

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0
for ip in $(curl -s https://www.cloudflare.com/ips-v4) $(curl -s https://www.cloudflare.com/ips-v6); do
  ufw allow proto tcp from $ip to any port 80,443
done
ufw enable
```

Server B: the same. Coolify's ports 8000, 6001 and 6002 stay closed to the public and are reachable only over `tailscale0`.

- Mirror the rules in the **Hostinger panel firewall**, which filters before traffic reaches the server. Remove the public port 22 rule only after SSH over Tailscale is confirmed working.
- Docker bypasses ufw for published ports. Never publish a database or Redis port on the host (Coolify does not unless "public" is ticked).
- Cloudflare's IP ranges change occasionally. Re-run the loop every few months.

### 4.4 Install Coolify on server B

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Open `http://<B-tailscale-ip>:8000`, then:

1. Create the admin account and **enable 2FA**.
2. *Servers → Add*: server A, using **A's Tailscale IP**, root, Coolify's generated key.
3. *Settings*: auto-update **off** (update by hand after reading release notes), Docker cleanup **on**.
4. *Sources*: connect the **GitHub App** for `agent1o1-api` and `agent1o1-admin`.
5. *Registries*: add GHCR with a token that has `read:packages`.
6. *Storages (S3)*: add R2 (`https://<account>.r2.cloudflarestorage.com`) and **Validate**.
7. *Keys & Tokens*: create an **API token** for GitHub Actions deploys.
8. Create project `agent1o1` with environments `production` (server A) and `staging` (server B).

### 4.5 Cloudflare

- [ ] DNS, all **proxied**: `api`, `ws`, `admin` → A's public IP; `errors`, `staging-api`, `staging-ws` → B's public IP; `app` → CNAME to Netlify.
- [ ] SSL/TLS **Full (strict)**. Create an **Origin Certificate** (15 years, `*.yourdomain.com` + apex) and install it as the default certificate in each server's Coolify proxy (Traefik). No Let's Encrypt renewals behind the proxy.
- [ ] *Always Use HTTPS*, minimum TLS 1.2. Enable **HSTS** after a week without problems.
- [ ] Cache rule: **bypass** for `api.*`, `ws.*`, `admin.*`, `errors.*`.
- [ ] WAF rate limiting: auth endpoints (login, register, password reset) around 10 requests/min per IP; webhook endpoints with a higher limit.
- [ ] **Cloudflare Access** (Zero Trust, free): protect `admin.`, `staging-api.`, `staging-ws.` and optionally `errors.`, allowing only your own login.
- [ ] WebSockets on (default).
- [ ] Free-plan limits to design around: **100 MB** maximum request body, and **100 s** until the origin's first response byte (error 524). Long AI work must stream or go through queues and Reverb. Test the slowest synchronous endpoint.

---

## 5. Phase 2: Data on server A (half a day)

### 5.1 Postgres

Coolify → *production* → *New resource* → **PostgreSQL** on server A:

- Image `pgvector/pgvector:pg17` (same as CI). Database `agent1o1`, strong password, **no public port**.
- As the admin user:

  ```sql
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE ROLE app LOGIN PASSWORD '...';
  GRANT ALL ON DATABASE agent1o1 TO app;
  ALTER DATABASE agent1o1 OWNER TO app;
  ```

  The app connects as `app`, never as a superuser.
- Tuning for 16 GB shared with the app: `max_connections=100`, `shared_buffers=2GB`, `effective_cache_size=6GB`, `work_mem=16MB`.

Backups, three independent layers:

| Layer | Tool | Destination | Worst-case data loss |
| --- | --- | --- | --- |
| 1. Continuous | **WAL-G** WAL archiving + nightly base backup | R2 `agent1o1-backups/wal` | About 1 to 5 minutes (point-in-time restore) |
| 2. Daily dump | Coolify scheduled backup `0 3 * * *`, keep 14 local / 30 in S3 | R2 | 24 h |
| 3. Immutable | `spatie/laravel-backup`, nightly | AWS Object Lock bucket | 24 h, cannot be deleted even if the server is compromised |

Layer 1 needs a small custom image (pgvector:pg17 + the WAL-G binary + `archive_command`). Layers 2 and 3 from day one; layer 1 before taking real payments.

### 5.2 Two Redis instances

Two **Redis 7** resources on A, no public port, strong passwords:

| | `redis-queue` | `redis-cache` |
| --- | --- | --- |
| Holds | Horizon queues, cache locks, admin sessions | App cache, rate limiters |
| Config | `appendonly yes`, `appendfsync everysec`, `maxmemory 1gb`, `maxmemory-policy noeviction` | `appendonly no`, `save ""`, `maxmemory 512mb`, `maxmemory-policy allkeys-lru` |
| If lost | In-flight jobs lost; reaper jobs clean up stuck runs | Harmless, refills |

Queued jobs must never be evicted to make room; cache entries should be.

Code change needed: queues already read `REDIS_QUEUE_CONNECTION`. Giving the cache its own host needs `REDIS_CACHE_HOST` / `REDIS_CACHE_PASSWORD` in `config/database.php`, and `cache.stores.redis.lock_connection` pointed at the queue instance, so `onOneServer()` locks and cache-driven maintenance mode live in the instance that never evicts.

---

## 6. Phase 3: Build and deploy pipeline (half a day)

### 6.1 GitHub Actions (agent1o1-api)

Extend the existing CI (`.github/workflows/ci.yml`: Pest, Pint, PHPStan, `composer audit`, Postgres migrate/rollback) with a deploy workflow on push to `staging` and `main`:

1. **Test:** the existing CI jobs must pass.
2. **Build:** matrix over `web | horizon | scheduler | reverb`, `docker/build-push-action` with the GitHub Actions layer cache, pushed to `ghcr.io/aipilotbyjd/agent1o1-api-<service>:<git-sha>` plus `:staging` or `:production`.
3. **Deploy:** runs in the `staging` or `production` GitHub environment (production waits for approval). Joins Tailscale with `tailscale/github-action` and an ephemeral key (Coolify is private), then calls Coolify **in order**, waiting for each to be healthy:

   ```bash
   curl -fsS -H "Authorization: Bearer $COOLIFY_TOKEN" \
     "http://<B-tailscale-ip>:8000/api/v1/deploy?uuid=$WEB_UUID"
   # then HORIZON_UUID, SCHEDULER_UUID, REVERB_UUID
   ```

GitHub secrets: `COOLIFY_TOKEN`, `TS_OAUTH_CLIENT_ID`, `TS_OAUTH_SECRET`, and the resource UUIDs.

### 6.2 agent1o1-admin

Same workflow with one image, `agent1o1-admin:<sha>` (the Node assets stage is already in its Dockerfile).

### 6.3 Frontend (Netlify)

- Production branch `main` with production `VITE_*` values; branch deploy `staging` with staging values.
- Build command runs lint before build so a broken build never goes live.

### 6.4 Release rules

- **Backward-compatible migrations only:** add column → deploy → backfill → drop the old column in a later release. Then an immediate rollback is always safe.
- **Rollback** = deploy the previous `<sha>` tag in Coolify. Do not roll back migrations under pressure.
- **Order:** web (runs migrations) → horizon → scheduler → reverb → admin.

---

## 7. Phase 4: Production apps on server A (1 day)

Each is a **Docker Image** resource in the `production` environment on server A, pulling the `:production` tag from GHCR.

### 7.1 Shared variables

Set as Coolify environment-level shared variables and reference them from every API resource so the four services cannot drift apart:

```dotenv
# Core
APP_NAME=agent1o1
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                     # also in password manager
APP_URL=https://api.yourdomain.com
APP_FRONTEND_URL=https://app.yourdomain.com
CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
LOG_CHANNEL=stderr
LOG_LEVEL=warning
BCRYPT_ROUNDS=12
APP_MAINTENANCE_DRIVER=cache
TRUSTED_PROXIES=<Cloudflare ranges>,10.0.0.0/8,172.16.0.0/12

# Database
DB_CONNECTION=pgsql
DB_HOST=<postgres internal hostname from Coolify>
DB_PORT=5432
DB_DATABASE=agent1o1
DB_USERNAME=app
DB_PASSWORD=...

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=<redis-queue internal host>
REDIS_PASSWORD=...
REDIS_CACHE_HOST=<redis-cache internal host>      # after the config change in 5.2
REDIS_CACHE_PASSWORD=...
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.yourdomain.com

# Files
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=agent1o1-files
AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# Realtime
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=ws.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://app.yourdomain.com
REVERB_SCALING_ENABLED=true

# Auth (Passport keys identical on every container)
PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----..."
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
# GOOGLE_*, GITHUB_* with production redirect URIs

# Mail, billing, AI, alerts, backups, observability
MAIL_MAILER=resend
RESEND_API_KEY=...
MAIL_FROM_ADDRESS=hello@yourdomain.com
# STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_PLAN_* (live values)
# AI provider keys: only the providers you use
PLATFORM_DAILY_AI_BUDGET_USD=...
FREE_TIER_DAILY_BUDGET_USD=...
ADMIN_ALERTS_ENABLED=true
ADMIN_ALERT_DISCORD_WEBHOOK=...
BACKUPS_ENABLED=true
# BACKUP_AWS_* (Object Lock account), BACKUP_ARCHIVE_PASSWORD
# SENTRY_LARAVEL_DSN=https://...@errors.yourdomain.com/1   (after the SDK is added)
# POSTHOG_ENABLED, POSTHOG_PROJECT_TOKEN, POSTHOG_HOST
SUPER_ADMIN_ENABLED=false
```

`TRUSTED_PROXIES` lists Cloudflare's ranges and the Docker networks, never `*`. That is only safe because the firewall in 4.3 lets nothing but Cloudflare reach the server.

### 7.2 Per-resource settings

| Resource | Image | Port / domain | Settings for this resource only |
| --- | --- | --- | --- |
| **api-web** | `agent1o1-api-web` | 8080 → `https://api.yourdomain.com` | `AUTORUN_ENABLED=true` (**only here**: migrations and config/route/view caching). Health check `/up`. Rolling updates. Limits: 2 CPU, 2 GB. |
| **api-reverb** | `agent1o1-api-reverb` | 8000 → `https://ws.yourdomain.com` | Health check `/up`. `--ulimit nofile=65535:65535`. Limit 512 MB. |
| **api-horizon** | `agent1o1-api-horizon` | none | **Stop grace period 1260 s**, matching the 1200 s `ai-assistant` timeout (`config/horizon.php`, `ASSISTANT_QUEUE_RETRY_AFTER=1260`) so deploys do not kill running assistant jobs. If that makes deploys too slow, lower it deliberately and rely on retries and the reaper jobs. Worker caps through `HORIZON_*_MAX_PROCESSES` (the KVM 4 column in `docs/HOSTING_PLAN_SINGLE_SERVER.md` §7.3, about 31 workers); the defaults allow 70, too many for 16 GB shared with Postgres. Limit 4 GB. |
| **api-scheduler** | `agent1o1-api-scheduler` | none | **Exactly one replica.** Limit 256 MB. |
| **admin** | `agent1o1-admin` | 8080 → `https://admin.yourdomain.com` | `AUTORUN_ENABLED=true`, `REDIS_HOST`=redis-queue, `REDIS_DB=2`, `REDIS_CACHE_DB=3`, `REDIS_PREFIX=admin_`. Post-deploy `php artisan admin:preflight`. `SUPER_ADMIN_ALLOWED_IPS`, `SUPER_ADMIN_HOST`. Behind Cloudflare Access. |

### 7.3 First launch

Once, in the api-web terminal in Coolify:

```bash
php artisan migrate --force
php artisan setup:catalog:sync --dry-run && php artisan setup:catalog:sync
php artisan setup:catalog:status
php artisan passport:client --password     # put the ID and secret into the env
php artisan horizon:status
php artisan super-admin:create you@yourdomain.com --role=owner   # if used
```

Then:

- Stripe: add the webhook endpoint on `https://api.yourdomain.com` and copy its secret into `STRIPE_WEBHOOK_SECRET`.
- OAuth apps (Google, GitHub, connectors): update redirect URLs to the production domains.
- Horizon and Pulse: their gates open only for addresses in `SUPER_ADMIN_DASHBOARD_IPS` (with `SUPER_ADMIN_ENABLED=true`). Confirm your address gets in and nobody else does.

### 7.4 User workflows and internal services

Workflows call arbitrary URLs. `App\Services\Http\SsrfGuard` already blocks private ranges, including Docker's `172.16.0.0/12`, with DNS pinning. As defense in depth, Postgres and both Redis instances have strong passwords and no public ports, so a bypass alone cannot use them.

---

## 8. Phase 5: Operations on server B (half a day)

### 8.1 GlitchTip

- Coolify one-click service → `https://errors.yourdomain.com`, with **its own** Postgres and Redis.
- Open registration off, event retention 30 days, email alerts on new issues.
- Projects: `api`, `admin`, `frontend`.
- Code change needed: `sentry/sentry-laravel` in api and admin, `@sentry/react` in the frontend, DSN from an env var, no request bodies or auth headers sent.

### 8.2 Uptime Kuma

Coolify one-click service, internal only (open it over Tailscale). Every 60 s:

| Monitor | Type |
| --- | --- |
| `https://api.yourdomain.com/up` | HTTP with keyword |
| `https://ws.yourdomain.com/up` | HTTP |
| `https://admin.yourdomain.com/up` | HTTP, through Cloudflare Access with a service token |
| `https://app.yourdomain.com` | HTTP |
| `https://errors.yourdomain.com` | HTTP |
| SSL expiry on all of the above | Certificate expiry |
| Scheduler heartbeat | *Push* monitor pinged every minute by a scheduled task |
| Horizon heartbeat | *Push* monitor pinged by a small job on the `default` queue, proving workers are processing |

Notifications go to the same Discord channel as `ADMIN_ALERT_DISCORD_WEBHOOK`.

### 8.3 UptimeRobot

Monitors `api.…/up` and server B's Uptime Kuma, so an outage of B is still noticed.

### 8.4 Staging

- Coolify environment `staging` on B: the four API resources and admin on the `:staging` images.
- Its own small Postgres (pgvector) and one Redis.
- Stripe **test** keys, a different `APP_KEY`, bucket `agent1o1-files-staging`, `MAIL_MAILER=log` or a sandbox.
- Domains `staging-api.` and `staging-ws.`, behind Cloudflare Access.

### 8.5 Coolify's own backup

*Settings → Backup*: daily to R2. If B is lost, reinstall Coolify and restore its configuration.

---

## 9. Phase 6: Monitoring and alerts

| What | Tool | Alert when |
| --- | --- | --- |
| Exceptions | GlitchTip | New issue or regression |
| Uptime and SSL | Uptime Kuma + UptimeRobot | Down for 2 checks in a row; SSL expires in under 14 days |
| Queues | Horizon + `waits` in `config/horizon.php` | `LongWaitDetected` to Discord |
| Scheduler or workers stopped | Heartbeat push monitors | No ping for 3 minutes |
| Performance | Laravel Pulse | Weekly review of slow queries, jobs and heavy users |
| Server resources | Coolify server metrics + Hostinger alerts | Disk > 80%, RAM > 85% for 10 minutes |
| AI spend | `PLATFORM_DAILY_AI_BUDGET_USD` + provider billing alerts | Budget reached |
| Failed jobs | `failed_jobs` + admin alerts | Any failed `billing-webhook` job or `BroadcastEvent` |

---

## 10. Phase 7: Pre-launch tests (1 day)

| # | Test | Passes when |
| --- | --- | --- |
| 1 | `curl -k https://<A public IP>` from outside | Connection refused |
| 2 | Open the Coolify URL with Tailscale off | Nothing loads |
| 3 | Hit `/up` through Cloudflare | Laravel logs your **real** IP |
| 4 | Open one workflow in two browsers as two members | Each sees the other's avatar |
| 5 | Deploy while connected to a websocket | Client reconnects automatically |
| 6 | Kill Horizon mid-run | The run retries or fails visibly, never stuck "running" |
| 7 | Run two schedulers briefly | Each task runs once (`onOneServer()` lock works) |
| 8 | Point-in-time restore to 10 minutes ago into a **new** resource | App boots with the same `APP_KEY`; encrypted fields are readable |
| 9 | Roll back to the previous image sha | Done in under 5 minutes |
| 10 | Stop server B | Production keeps working; UptimeRobot alerts |
| 11 | Send every email type | Arrives in the inbox (mail-tester ≥ 9/10) |
| 12 | Stripe test checkout, upgrade and cancel on staging | Webhooks processed, subscription state correct |
| 13 | Upload a large file | Rejected cleanly below Cloudflare's 100 MB limit |

---

## 11. Incidents

| Event | Data lost | Downtime | Response |
| --- | --- | --- | --- |
| Bad deploy | None | ~5 min | Deploy the previous sha in Coolify |
| Rows deleted or data corrupted | ~1 to 5 min | ~30 min | WAL-G point-in-time restore into a new resource, switch `DB_HOST` (runbook §4) |
| Redis lost | In-flight jobs | Minutes | Restart; reaper jobs fix stuck runs; check `failed_jobs` |
| Server A lost | ~1 to 5 min | ~1 to 2 h | New KVM 4 → add to Coolify → restore Postgres from R2 → redeploy images → env from the password manager → update DNS |
| Server B lost | None | Production unaffected | Reinstall Coolify on a new server, restore its backup from R2 |
| Server and Coolify compromised | ≤ 24 h | Hours | Rebuild from the Object Lock spatie backup and the password manager; rotate every secret |

Rehearse a restore before launch and then monthly (runbook §3).

---

## 12. Cost

| Item | Approx. |
| --- | --- |
| Hostinger KVM 4 + KVM 2 | Two VPS plans (check current pricing; longer terms are cheapest) |
| Cloudflare, Tailscale, UptimeRobot, GHCR, Netlify | Free plans |
| R2 | A few dollars, no egress fees |
| AWS Object Lock bucket | About $1 to $3 |
| Email | Free tier, then about $10 to $20 |

---

## 13. Scaling

| Sign | Next step |
| --- | --- |
| RAM on A above 75% for weeks, or Horizon queues backing up | Add a worker server in Coolify and move api-horizon there |
| Postgres CPU or disk is the bottleneck | Move Postgres to its own server or a managed Postgres, keeping WAL archiving |
| Web latency rises under load | Run 2+ api-web replicas (sessions and cache are in Redis, Passport keys are shared) |
| Thousands of websocket connections | 2+ api-reverb replicas (`REVERB_SCALING_ENABLED` is already on) |
| Builds are slow | Merge the four near-identical Dockerfiles into one image with four start commands |

---

## 14. Timeline

| Day | Work |
| --- | --- |
| 1 | Phases 0 to 2: accounts, hardening, Tailscale, firewalls, Coolify, Cloudflare, Postgres, Redis |
| 2 | Phase 3: GitHub Actions for api and admin, Netlify settings |
| 3 | Phase 4: production resources, env, first-launch commands, Stripe and OAuth |
| 4 | Phases 5 and 6: GlitchTip, Uptime Kuma, UptimeRobot, staging, alerts |
| 5 | Phase 7: all pre-launch tests, fix what fails |

---

## 15. Code changes this plan depends on

- [ ] Redis split: `REDIS_CACHE_HOST` / `REDIS_CACHE_PASSWORD` in `config/database.php`, lock connection on redis-queue in `config/cache.php` (5.2).
- [ ] Deploy workflow for api and admin: build, push to GHCR, deploy through Coolify over Tailscale (6.1, 6.2).
- [ ] Postgres image with pgvector and WAL-G, and point-in-time restore steps in `docs/BACKUP_RESTORE_RUNBOOK.md` (5.1).
- [ ] Sentry SDK for GlitchTip in api, admin and frontend (8.1).
- [ ] Scheduler and Horizon heartbeats to Uptime Kuma push URLs (8.2).
- [x] Horizon worker caps through `HORIZON_*_MAX_PROCESSES` (7.2).
