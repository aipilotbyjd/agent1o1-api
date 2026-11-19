# Hosting Plan: Single Server

How to run Agent1o1 (this API, `agent1o1-admin` and the `agent1o1` React frontend) in production on **one Hostinger VPS** while the product is early. Everything that does not have to live on the server uses free or cheap hosted services. When the triggers in §14 are reached, move to the two-server setup in `docs/HOSTING_PLAN.md`; nothing here has to be redone.

Written for a **KVM 2 (2 vCPU / 8 GB RAM / 100 GB disk)**. Where a value differs on a KVM 4 (4 vCPU / 16 GB), it is given in brackets as *[KVM 4: …]*.

Pair this with `docs/LAUNCH_CHECKLIST.md` (what must be true before launch) and `docs/BACKUP_RESTORE_RUNBOOK.md` (how to back up and restore). Menu names in Coolify, Cloudflare and Hostinger change between versions; the steps describe the intent.

Domains used below: `app.`, `api.`, `ws.` and `admin.` on your domain.

---

## 1. Architecture

```
              Cloudflare (free: DNS, SSL, WAF, hides the server IP)
         ┌──────────────┼────────────────┐
    app.domain    api. / ws. / admin.     (only Cloudflare IPs reach the server)
     Netlify            │
                        ▼
┌────────── Hostinger KVM 2 (2 vCPU / 8 GB / 100 GB) ──────────┐
│ Coolify (dashboard reachable only over Tailscale)            │
│  api-web        Dockerfile.web        :8080 → api.domain     │
│  api-reverb     Dockerfile.reverb     :8000 → ws.domain      │
│  api-horizon    Dockerfile.horizon    (capped, see §7.3)     │
│  api-scheduler  Dockerfile.scheduler  (exactly 1 replica)    │
│  admin          agent1o1-admin/Dockerfile :8080 → admin.     │
│  Postgres 17 + pgvector (private)   Redis 7 (private)        │
└──────────────────────────────────────────────────────────────┘

Off-server:
  Cloudflare R2        → uploaded files + hourly database backups + Coolify backup
  Sentry (free plan)   → errors from api, admin and frontend
  UptimeRobot (free)   → uptime, SSL expiry, scheduler/Horizon heartbeats
  AWS S3 Object Lock   → immutable spatie backups (before taking payments)
  Password manager     → APP_KEY, Passport keys, every env var
```

How this differs from the two-server plan, and why:

| Two-server plan | Single server | Why |
| --- | --- | --- |
| Coolify on its own server | Coolify on the same VPS, dashboard only over Tailscale | Only one server; Tailscale keeps the dashboard off the internet |
| Self-hosted GlitchTip | **Sentry free plan** | No RAM used, and it still works when the server is down |
| Uptime Kuma on server B | **UptimeRobot free** | Monitoring must run somewhere other than the server it watches |
| Staging server | No staging yet; test locally with Stripe test mode | Saves RAM and time |
| WAL-G point-in-time recovery | **Hourly dumps** to R2 + nightly spatie | Simple; the database is still small |
| Two Redis instances | **One Redis**, `noeviction` | No code change; split when memory gets close |
| Builds in GitHub Actions | Builds in Coolify, one at a time, to start | No pipeline to build yet; move to Actions when deploys slow the app |

---

## 2. What to buy and sign up for

| Item | Choice | Purpose |
| --- | --- | --- |
| Server | Hostinger **KVM 2**, Ubuntu 24.04 | Everything that must run on a server |
| Private network | **Tailscale** (free) | SSH and the Coolify dashboard |
| DNS and edge | **Cloudflare** (free) | Proxy, WAF, origin certificate, Access |
| Object storage | **Cloudflare R2** | Buckets `agent1o1-files` (versioning on) and `agent1o1-backups` |
| Errors | **Sentry** (free plan) | Projects `api`, `admin`, `frontend` |
| Uptime | **UptimeRobot** (free) | HTTP, SSL and heartbeat monitors |
| Email | **Resend** or **Postmark** | Transactional mail with SPF, DKIM and DMARC |
| Frontend hosting | **Netlify** (already configured) | React app |
| Secrets | **Bitwarden** or **1Password** | Offline copy of every secret |
| Immutable backups | AWS S3 in a **separate account**, Object Lock in compliance mode | `spatie/laravel-backup` (runbook §2.6). Can wait until you take payments |

---

## 3. Phase 0: Accounts (1 hour)

- [ ] Move the domain's DNS to Cloudflare.
- [ ] Create the R2 buckets and an R2 API token limited to them.
- [ ] Create the Sentry projects and note each DSN.
- [ ] Create the UptimeRobot account.
- [ ] Set up the email provider and verify the sending domain.
- [ ] Install Tailscale on your laptop and phone.
- [ ] Create a password manager vault `agent1o1-production`.

---

## 4. Phase 1: Server (2 hours)

### 4.1 Harden

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

On 8 GB the **4 GB swap is required**, not optional: it absorbs build spikes instead of letting the kernel kill Postgres.

### 4.2 Tailscale

```bash
curl -fsSL https://tailscale.com/install.sh | sh && tailscale up
```

Note the server's Tailscale IP (`100.x.y.z`).

### 4.3 Firewall

Web traffic only from Cloudflare, everything else only over Tailscale:

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0
for ip in $(curl -s https://www.cloudflare.com/ips-v4) $(curl -s https://www.cloudflare.com/ips-v6); do
  ufw allow proto tcp from $ip to any port 80,443
done
ufw enable
```

- Mirror the rules in the **Hostinger panel firewall**. Remove the public port 22 rule only after SSH over Tailscale works.
- Docker bypasses ufw for published ports. Never publish a database or Redis port on the host.
- Re-run the Cloudflare loop every few months; their ranges change occasionally.

### 4.4 Hostinger backups

Keep the VPS backup schedule on **weekly**, and take a manual **snapshot** before risky changes (Coolify upgrades, Postgres major versions, OS upgrades).

---

## 5. Phase 2: Coolify and Cloudflare (1 to 2 hours)

### 5.1 Coolify

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Open `http://<tailscale-ip>:8000`, then:

1. Create the admin account and **enable 2FA**.
2. *Settings*: **concurrent builds 1**, auto-update **off** (update by hand after reading release notes), Docker cleanup **on** (100 GB fills with old images).
3. *Sources*: connect the **GitHub App** for `agent1o1-api` and `agent1o1-admin`.
4. *Storages (S3)*: add R2 (`https://<account>.r2.cloudflarestorage.com`) and **Validate**.
5. *Settings → Backup*: Coolify's own backup, daily, to R2.
6. Create project `agent1o1` with environment `production` on this server.

Ports 8000, 6001 and 6002 stay closed to the public; the firewall in 4.3 already allows them only over Tailscale.

### 5.2 Cloudflare

- [ ] DNS, all **proxied**: `api`, `ws`, `admin` → the server's public IP; `app` → CNAME to Netlify.
- [ ] SSL/TLS **Full (strict)**. Create an **Origin Certificate** (15 years, `*.yourdomain.com` + apex) and install it as the default certificate in Coolify's proxy.
- [ ] *Always Use HTTPS*, minimum TLS 1.2. Enable **HSTS** after a week without problems.
- [ ] Cache rule: **bypass** for `api.*`, `ws.*`, `admin.*`.
- [ ] WAF rate limiting on login, register and password reset: about 10 requests/min per IP.
- [ ] **Cloudflare Access** (Zero Trust, free) on `admin.`, allowing only your own login.
- [ ] WebSockets on (default).
- [ ] Free-plan limits to design around: **100 MB** maximum request body, **100 s** until the origin's first response byte (error 524). Long AI work must stream or go through queues and Reverb.

---

## 6. Phase 3: Postgres and Redis (1 hour)

### 6.1 Postgres

Coolify → *production* → *New resource* → **PostgreSQL**:

- Image `pgvector/pgvector:pg17` (same as CI). Database `agent1o1`, strong password, **no public port**.
- As the admin user:

  ```sql
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE ROLE app LOGIN PASSWORD '...';
  GRANT ALL ON DATABASE agent1o1 TO app;
  ALTER DATABASE agent1o1 OWNER TO app;
  ```

- Tuning: `max_connections=50`, `shared_buffers=512MB`, `effective_cache_size=2GB`, `work_mem=8MB` *[KVM 4: 60, 1GB, 4GB, 16MB]*.
- Backups: **hourly** (`0 * * * *`), *Save to S3* on R2, keep 48 local and 30 days in S3. Run **Backup Now** and confirm the file is in the bucket.

`max_connections=50` covers: Horizon workers (§7.3, 18 at most) + php-fpm children (8 web, 4 admin) + Reverb + scheduler + headroom.

### 6.2 Redis

One **Redis 7** resource, strong password, no public port. Custom config:

```
appendonly yes
appendfsync everysec
maxmemory 384mb
maxmemory-policy noeviction
```

*[KVM 4: `maxmemory 768mb`]*

`noeviction` means queued jobs are never dropped to make room. The trade-off: when Redis is full, writes fail loudly instead of losing data silently. Alert at 80% of `maxmemory`; when it gets close, split cache and queues into two instances as in `docs/HOSTING_PLAN.md` §5.2.

---

## 7. Phase 4: Apps (half a day)

### 7.1 Memory budget

| Service | Limit | How |
| --- | --- | --- |
| OS + Docker | ~0.5 GB | |
| Coolify (dashboard, its database, proxy) | ~1.2 GB | |
| Postgres | 1.5 GB | §6.1 |
| Redis | 512 MB | `maxmemory 384mb` |
| api-web | 768 MB | `PHP_FPM_PM_MAX_CHILDREN=8` |
| api-horizon | 1.8 GB | Worker caps in §7.3 |
| api-reverb | 256 MB | |
| api-scheduler | 192 MB | |
| admin | 384 MB | `PHP_FPM_PM_MAX_CHILDREN=4` |
| **Total** | **~7.1 GB** | Plus 4 GB swap for builds and spikes |

*[KVM 4: api-web 1.5 GB with 16 children, api-horizon 3 GB, Postgres 3 GB, Redis 1 GB; total about 11 GB.]*

### 7.2 Shared variables

Set as Coolify environment-level shared variables and reference them from every API resource:

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

# Redis (one instance)
REDIS_CLIENT=phpredis
REDIS_HOST=<redis internal hostname from Coolify>
REDIS_PASSWORD=...
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
# SENTRY_LARAVEL_DSN=...   (after the Sentry SDK is added)
# POSTHOG_ENABLED, POSTHOG_PROJECT_TOKEN, POSTHOG_HOST
SUPER_ADMIN_ENABLED=false
SUPER_ADMIN_DASHBOARD_IPS=<your Tailscale or home IP>   # who may open /horizon and /pulse
```

`TRUSTED_PROXIES` lists Cloudflare's ranges and the Docker networks, never `*`. That is only safe because the firewall in 4.3 lets nothing but Cloudflare reach the server.

### 7.3 Horizon worker caps

In production, `config/horizon.php` lets Horizon scale to **70 worker processes**. Each is a PHP process of about 60 to 100 MB with its own database connection, so 70 workers would need 4 to 7 GB and exhaust an 8 GB server at the first spike. Every production supervisor's ceiling reads a `HORIZON_*_MAX_PROCESSES` variable. Set these on **api-horizon only**:

| Variable | KVM 2 | KVM 4 | Default |
| --- | --- | --- | --- |
| `HORIZON_DEFAULT_MAX_PROCESSES` | 2 | 4 | 10 |
| `HORIZON_TRIGGERS_EVENT_MAX_PROCESSES` | 1 | 2 | 5 |
| `HORIZON_TRIGGERS_POLL_MAX_PROCESSES` | 1 | 1 | 3 |
| `HORIZON_WORKFLOWS_DISPATCH_MAX_PROCESSES` | 1 | 2 | 5 |
| `HORIZON_WORKFLOWS_EXECUTE_MAX_PROCESSES` | 3 | 5 | 10 |
| `HORIZON_AI_AGENT_MAX_PROCESSES` | 2 | 3 | 3 |
| `HORIZON_AI_SUBAGENT_MAX_PROCESSES` | 2 | 4 | 10 |
| `HORIZON_AI_ASSISTANT_MAX_PROCESSES` | 2 | 4 | 10 |
| `HORIZON_WORKFLOW_BUILDER_MAX_PROCESSES` | 1 | 2 | 5 |
| `HORIZON_BILLING_MAX_PROCESSES` | 1 | 1 | 3 |
| `HORIZON_BROADCASTS_MAX_PROCESSES` | 1 | 2 | 3 |
| `HORIZON_LONG_RUNNING_MAX_PROCESSES` | 1 | 1 | 3 |
| **Total** | **18** (~1.4 GB) | **31** (~2.5 GB) | 70 |

With caps, a spike makes jobs wait a little longer in the queue instead of crashing the server. AI jobs mostly wait on provider APIs, so a few workers per AI queue go a long way. If you raise the caps, raise Postgres `max_connections` to match.

### 7.4 Resources

Each is an *Application* from GitHub with the **Dockerfile** build pack, branch `main`, **auto-deploy off** (deploy by hand).

| Resource | Dockerfile | Port / domain | Settings for this resource only |
| --- | --- | --- | --- |
| **api-web** | `Dockerfile.web` | 8080 → `https://api.yourdomain.com` | `AUTORUN_ENABLED=true` (**only here**: migrations and config/route/view caching). Health check `/up`. `PHP_FPM_PM_MAX_CHILDREN=8`. Limit 768 MB. |
| **api-reverb** | `Dockerfile.reverb` | 8000 → `https://ws.yourdomain.com` | Health check `/up`. `--ulimit nofile=65535:65535`. Limit 256 MB. |
| **api-horizon** | `Dockerfile.horizon` | none | The caps in §7.3. **Stop grace period 1260 s**, matching the 1200 s `ai-assistant` timeout, so deploys do not kill running assistant jobs. Limit 1.8 GB. |
| **api-scheduler** | `Dockerfile.scheduler` | none | **Exactly one replica.** Limit 192 MB. |
| **admin** | `agent1o1-admin/Dockerfile` | 8080 → `https://admin.yourdomain.com` | `AUTORUN_ENABLED=true`, `REDIS_HOST` = the Redis above, `REDIS_DB=2`, `REDIS_CACHE_DB=3`, `REDIS_PREFIX=admin_`, `PHP_FPM_PM_MAX_CHILDREN=4`. Post-deploy `php artisan admin:preflight`. `SUPER_ADMIN_ALLOWED_IPS`, `SUPER_ADMIN_HOST`. Behind Cloudflare Access. Limit 384 MB. |

### 7.5 Builds and deploys

- Each API deploy builds four images, one at a time (concurrency 1). Unchanged Docker layers are reused, so later builds are much faster than the first. **Deploy when traffic is low.**
- **Order:** api-web (runs migrations) → api-horizon → api-scheduler → api-reverb → admin.
- **Backward-compatible migrations only:** add column → deploy → backfill → drop the old column in a later release, so a rollback is always safe.
- **Rollback:** redeploy the previous commit in Coolify.
- When builds start slowing the live app (CPU pegged during deploys), move builds to GitHub Actions and GHCR as in `docs/HOSTING_PLAN.md` §6. Coolify then only pulls images.

### 7.6 First launch

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
- Open `/horizon` and `/pulse` from an address in `SUPER_ADMIN_DASHBOARD_IPS` and confirm nobody else gets in.

### 7.7 User workflows and internal services

Workflows call arbitrary URLs. `App\Services\Http\SsrfGuard` already blocks private ranges, including Docker's `172.16.0.0/12`, with DNS pinning. As defense in depth, Postgres and Redis have strong passwords and no public ports.

---

## 8. Phase 5: Frontend on Netlify (30 minutes)

- Environment variables: `VITE_API_URL=https://api.yourdomain.com`, `VITE_REVERB_HOST=ws.yourdomain.com`, `VITE_REVERB_PORT=443`, `VITE_REVERB_APP_KEY`, `VITE_POSTHOG_HOST`, `VITE_POSTHOG_PROJECT_TOKEN`.
- Build command runs lint before build so a broken build never goes live.
- Custom domain `app.yourdomain.com`.

---

## 9. Phase 6: Monitoring (1 hour, all free)

| What | Tool | Alert when |
| --- | --- | --- |
| Exceptions in api, admin, frontend | **Sentry** free plan | New issue or regression |
| Uptime | **UptimeRobot**: `api.…/up`, `ws.…/up`, `admin.…/up`, `app.…`, every 5 minutes | Down for 2 checks |
| SSL | UptimeRobot SSL monitors | Expires in under 14 days |
| Scheduler stopped | UptimeRobot **heartbeat** pinged every minute by a scheduled task | No ping for 3 minutes |
| Horizon stopped | UptimeRobot **heartbeat** pinged by a small job on the `default` queue | No ping for 3 minutes |
| Queues backing up | Horizon `waits` → `LongWaitDetected` to Discord | Wait threshold exceeded |
| Performance | Laravel Pulse | Weekly review |
| Server disk and RAM | Coolify server metrics + Hostinger alerts | Disk > 80%, RAM > 85% for 10 minutes, swap in constant use |
| Redis memory | Coolify metrics / `INFO memory` | Above 80% of `maxmemory` |
| AI spend | `PLATFORM_DAILY_AI_BUDGET_USD` + provider billing alerts | Budget reached |
| Failed jobs | `failed_jobs` + admin alerts | Any failed `billing-webhook` job or `BroadcastEvent` |

---

## 10. Backups

| What | How | How often | Worst-case loss |
| --- | --- | --- | --- |
| Postgres | Coolify dump to R2 | **Hourly** | 1 hour |
| Postgres (immutable) | `spatie/laravel-backup` to the Object Lock bucket | Nightly; set up before taking payments | 24 hours, cannot be deleted |
| Uploaded files | R2 bucket versioning | Continuous | None |
| Coolify configuration | Coolify backup to R2 | Daily | 24 hours |
| Whole server | Hostinger VPS backup / snapshot | Weekly, and before risky changes | 1 week |
| Secrets | Password manager | Every change | None |

**Rehearse a restore** before launch and then monthly (runbook §3): restore the latest dump into a new Postgres resource and confirm the app reads encrypted fields with the same `APP_KEY`.

---

## 11. Phase 7: Pre-launch tests (half a day)

| # | Test | Passes when |
| --- | --- | --- |
| 1 | `curl -k https://<server public IP>` from outside | Connection refused |
| 2 | Open the Coolify URL with Tailscale off | Nothing loads |
| 3 | Hit `/up` through Cloudflare | Laravel logs your **real** IP |
| 4 | Open one workflow in two browsers as two members | Each sees the other's avatar |
| 5 | Deploy while connected to a websocket | Client reconnects automatically |
| 6 | Kill Horizon mid-run | The run retries or fails visibly, never stuck "running" |
| 7 | Queue 50 jobs at once | Horizon stays at its caps; RAM stays below 85% |
| 8 | Restore the latest dump into a new resource | App boots with the same `APP_KEY`; encrypted fields readable |
| 9 | Redeploy the previous commit | Done in under 10 minutes |
| 10 | Send every email type | Arrives in the inbox (mail-tester ≥ 9/10) |
| 11 | Stripe test checkout, upgrade and cancel (locally, test mode) | Webhooks processed, subscription state correct |
| 12 | Upload a large file | Rejected cleanly below Cloudflare's 100 MB limit |

---

## 12. Incidents

| Event | Data lost | Downtime | Response |
| --- | --- | --- | --- |
| Bad deploy | None | ~10 min | Redeploy the previous commit in Coolify |
| Rows deleted or data corrupted | ≤ 1 hour | ~30 min | Restore the last hourly dump into a new resource, switch `DB_HOST` (runbook §4) |
| Redis lost | In-flight jobs | Minutes | Restart; reaper jobs fix stuck runs; check `failed_jobs` |
| Server out of memory | None | Minutes | Check Horizon caps and swap; lower caps or upgrade to KVM 4 |
| Disk full | None | Minutes | Coolify Docker cleanup, prune old images and local backups |
| Server lost | ≤ 1 hour | ~2 hours | New VPS → install Coolify → restore Coolify's backup from R2 → restore Postgres from R2 → env from the password manager → update DNS |
| Server compromised | ≤ 24 hours | Hours | Rebuild from the Object Lock spatie backup and the password manager; rotate every secret |

---

## 13. Cost

| Item | Approx. |
| --- | --- |
| Hostinger KVM 2 | The plan you already pay for |
| Cloudflare, Tailscale, Sentry, UptimeRobot, Netlify | Free plans |
| R2 | A few dollars, no egress fees |
| AWS Object Lock bucket | About $1 to $3, once set up |
| Email | Free tier, then about $10 to $20 |

---

## 14. When to grow

| Sign | Next step |
| --- | --- |
| RAM above 80% for days, or swap used constantly | **Upgrade the VPS to KVM 4** in Hostinger (in place, no rebuild) and use the KVM 4 values in this document |
| CPU above 70% most of the time, or the API slows during deploys | Upgrade to KVM 4, and/or move builds to GitHub Actions (`docs/HOSTING_PLAN.md` §6) |
| Queues regularly wait too long | Raise the Horizon caps (§7.3) if RAM allows; otherwise upgrade |
| Redis memory close to `maxmemory` | Split cache and queues into two Redis instances (`docs/HOSTING_PLAN.md` §5.2) |
| Paying customers, and an hour of data loss is no longer acceptable | Add WAL-G point-in-time recovery (`docs/HOSTING_PLAN.md` §5.1) |
| You need a staging environment, or want Coolify off the production server | Add a second server and move to **`docs/HOSTING_PLAN.md`** |

---

## 15. Timeline

| Day | Work |
| --- | --- |
| 1 | Phases 0 to 3: accounts, hardening, Tailscale, firewall, Coolify, Cloudflare, Postgres, Redis, first backup in R2 |
| 2 | Phases 4 to 7: apps with memory limits and Horizon caps, first-launch commands, Stripe and OAuth, Netlify, Sentry, UptimeRobot, pre-launch tests |

---

## 16. Code changes this plan depends on

- [x] Horizon worker caps through `HORIZON_*_MAX_PROCESSES` (§7.3).
- [ ] Sentry SDK in api, admin and frontend (§9).
- [ ] Scheduler and Horizon heartbeat pings to UptimeRobot (§9).
