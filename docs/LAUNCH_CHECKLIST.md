# Launch Checklist

Go-live checklist for the first production launch of Agent1o1 API. Work top to bottom: sections 1-9 are **blockers** (do not take real users until done), sections 10-14 are **launch-week** items, 15+ are **post-launch** habits.

Legend: `[ ]` to do · **BLOCKER** = must be done before any external user · **VERIFY** = the repo suggests it exists, confirm it works in production.

Findings from reading the repo when this was written (re-check, they may be stale):
- No `.github/workflows` directory exists, so there is **no CI** yet (§12).
- Horizon's `viewHorizon` and Pulse's `viewPulse` gates are closed in production unless the request comes from an address in `SUPER_ADMIN_DASHBOARD_IPS` (and `SUPER_ADMIN_ENABLED=true`): **VERIFY** your own address gets in and nobody else does, and put both behind a VPN or Cloudflare Access as well (§3.3).
- Production services are four images: `Dockerfile.web` (nginx + php-fpm, `/up` health check), `Dockerfile.horizon` (`artisan horizon`), `Dockerfile.scheduler` (`schedule:work`), `Dockerfile.reverb` (`reverb:start` on :8000).
- `.env.example` defaults are all development values (sqlite, database queue/cache, `BROADCAST_CONNECTION=log`, `MAIL_MAILER=log`, local disk, `LOG_LEVEL=debug`).

---

## 0. Decisions to make first

Write the answer to each in one line at the top of your launch notes. They change priorities everywhere below.

- [ ] **Who is the first customer?** (solo builders, agencies, ops teams, developers)
- [ ] **Launch shape:** private beta (invite-only / waitlist) → public. Recommended: private beta first.
- [ ] **Launch date and a rollback date:** the point at which you decide "pause sign-ups".
- [ ] **Who is on call** for the first two weeks, and the backup person.
- [ ] **Free tier policy:** exists or not, what it includes, and its hard limits (it is the main abuse and cost vector).
- [ ] **Success metrics at 30 / 90 days** (activation, weekly active workspaces, paid conversion, gross margin) and the "stop or double down" threshold.
- [ ] **Regions / data residency** you will serve, which drives legal pages and hosting region.

---

## 1. Environment and configuration (BLOCKER)

### 1.1 Core
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`, `APP_FRONTEND_URL`.
- [ ] `APP_KEY` generated and **stored in a secrets manager and a second offline location**. Losing it makes every encrypted value unreadable (BYOK keys, connector tokens, 2FA secrets, `Secret` rows).
- [ ] Decide the rotation story for `APP_KEY` before launch (`APP_PREVIOUS_KEYS` support) and write it in the runbook.
- [ ] `LOG_LEVEL=warning` (or `error`), `LOG_STACK=daily` or a shipping channel (Sentry/Flare/Nightwatch/stdout to your log platform). Never `debug` in production.
- [ ] `BCRYPT_ROUNDS` ≥ 12.
- [ ] `APP_LOCALE`/timezone decisions made (the app has per-user timezone handling; keep server time in UTC).

### 1.2 Data stores
- [ ] `DB_CONNECTION=pgsql` with production host/credentials (the web image installs `pdo_pgsql`). Not sqlite.
- [ ] Postgres: managed service, automated backups + PITR, TLS required, least-privilege app user (no superuser), connection limit sized for **web + Horizon + scheduler + Reverb** combined.
- [ ] pgvector (or whatever `document_embeddings` needs) installed and migrated on the production DB. **VERIFY** with a staging restore.
- [ ] `REDIS_*` set; `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` (Horizon needs Redis; `onOneServer()` in `routes/console.php` needs a shared cache store).
- [ ] Redis: persistence on (AOF), password set, not exposed publicly, `maxmemory-policy` chosen deliberately (queues and cache must not be evicted silently; consider separate Redis DBs/instances for cache vs queues).
- [ ] `SESSION_DRIVER` set for production, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` correct, `SESSION_SAME_SITE` checked against your frontend domain setup.

### 1.3 Files and storage
- [ ] `FILESYSTEM_DISK` points to S3-compatible storage. Container disks are ephemeral: uploads, exports (PDF/Word/Excel), knowledge-base documents and artifacts would be lost on redeploy.
- [ ] Bucket is private, has lifecycle rules, encryption at rest, and a scoped IAM user. Signed URLs only for downloads.
- [ ] `storage/` and `bootstrap/cache` writable by the container user; no persistent data expected on local disk.

### 1.4 Email
- [ ] Real `MAIL_MAILER` (SES/Postmark/Resend…), `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` on your own domain.
- [ ] SPF, DKIM, DMARC (start `p=none`, move to `quarantine`) configured and verified (mail-tester or similar score ≥ 9/10).
- [ ] Bounce and complaint handling wired (SNS/webhook) so you stop mailing bad addresses.
- [ ] Test every email type: verification, password reset, invitation, trial ending, referral digest, failure alerts, admin alerts (`php artisan` admin alert test command exists).
- [ ] Inbound email for the assistant (`ASSISTANT_EMAIL_INBOUND_TOKEN`, `ASSISTANT_EMAIL_REQUIRE_AUTH=true`) configured and tested end to end if you launch that feature.

### 1.5 Realtime (Reverb)
- [ ] `BROADCAST_CONNECTION=reverb` (not `log`).
- [ ] `REVERB_APP_ID/KEY/SECRET` generated (strong, unique), `REVERB_HOST`, `REVERB_SCHEME=https`, public port 443 behind your proxy with websocket upgrade headers and long read timeouts.
- [ ] `REVERB_ALLOWED_ORIGINS` and `CORS_ALLOWED_ORIGINS` list **only** your real frontend origin(s). No wildcards.
- [ ] `VITE_REVERB_*` values in the **frontend build** match production.
- [ ] Reverb scaling decision: single instance is fine for beta; if you scale horizontally, set `REVERB_SCALING_ENABLED=true` (Reverb's Redis scaling) and use sticky/health-checked routing.
- [ ] The `broadcasts` queue is consumed (first queue of `supervisor-1` in `config/horizon.php`) and `redis:broadcasts` in `waits` alerts a human. Queued broadcasts retry 3 times, so check `failed_jobs` for `BroadcastEvent` after any Reverb outage.
- [ ] Presence works through the proxy: open the same workflow as two members and confirm each sees the other's avatar.
- [ ] Load-check: how many concurrent websocket connections one container handles; raise the file descriptor limit accordingly.

### 1.6 Auth and OAuth clients
- [ ] Passport keys generated (`php artisan passport:keys`) and **identical across every web container** (store as secrets; do not generate per container, it invalidates tokens on each deploy).
- [ ] `PASSPORT_PASSWORD_CLIENT_ID/SECRET` created in the production DB and set.
- [ ] Token lifetimes reviewed (access, refresh, personal access) and match your security stance.
- [ ] Social login: Google and GitHub OAuth apps created for the **production** domain; redirect URIs match `GOOGLE_REDIRECT_URI` / `GITHUB_REDIRECT_URI` exactly; consent screens published (not "testing" mode).
- [ ] Connector OAuth apps (Google Workspace scopes, Slack, others you ship) created per environment, minimal scopes, and **submitted for provider verification early** (Google review for sensitive/restricted scopes can take weeks; Slack public distribution needs review).
- [ ] 2FA flow tested including recovery.

### 1.7 AI providers
- [ ] Production API keys for each provider you enable (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, and any of OpenRouter/Fireworks/Together/others). Separate keys from dev/staging.
- [ ] **Hard monthly spend limits and usage alerts set on every provider account.** This is your only backstop against a runaway loop.
- [ ] Provider tier/rate limits checked against expected beta concurrency; request limit increases ahead of time.
- [ ] `AI_EMBEDDINGS_PROVIDER` chosen and **fixed** before any knowledge source is synced (changing it requires re-syncing every source).
- [ ] `ModelCatalogDataset` content matches the models you actually have access to; run `ai-credentials:check`-style checks (scheduled hourly) and confirm they pass in production.
- [ ] Fallback model/provider configured for each critical path (workflow builder, agent turns, assistant).
- [ ] Decide and document what user data is sent to which provider, and whether any provider trains on it (needed for your privacy policy and customer questionnaires).

### 1.8 Third-party services you ship with
- [ ] `E2B_API_KEY` (code sandbox) present if `RunCodeNode`/code execution is enabled; otherwise the feature is **disabled** (see §3.6).
- [ ] Twilio (`TWILIO_*`, `ASSISTANT_SMS_FROM`) only if SMS ships; sender registration/compliance (A2P 10DLC, country rules) done.
- [ ] Assistant Slack app (`ASSISTANT_SLACK_*`) installed for the production workspace; signing secret verified.

### 1.9 Config hygiene
- [ ] `php artisan config:cache route:cache event:cache view:cache` runs cleanly in the image build/start.
- [ ] No `env()` calls outside `config/` (they return null once config is cached): `grep -rn "env(" app routes` should be empty.
- [ ] Every variable in `.env.example` is either set in production or intentionally left default. Add anything missing to `.env.example` (it is the contract).
- [ ] No secrets in the repository, `docs/`, `bruno/` environments, Dockerfiles, or git history (§3.9).

---

## 2. Infrastructure and deployment (BLOCKER)

### 2.1 Topology
- [ ] Four deployable units running: **web**, **horizon**, **scheduler**, **reverb**. Each has a health check and automatic restart.
- [ ] **Exactly one scheduler** process (`schedule:work`). Two schedulers are tolerated only because of `onOneServer()`; confirm cache is shared and the lock works (test by running two and checking single execution).
- [ ] Horizon: `php artisan horizon:terminate` on every deploy so workers pick up new code (supervisor/Coolify restarts the process).
- [ ] Horizon `memory`/`timeout`/`tries` per supervisor reviewed against the slowest job on that queue (`workflows-execute`, `ai-agent`, `ai-assistant`, `ai-subagent`, `workflow-builder`, `billing-webhook`, `triggers-event`, `triggers-poll`, `workflows-dispatch`, default/system queues).
- [ ] Queue `retry_after` is **greater than** the longest job timeout on that connection, or jobs will run twice.
- [ ] Horizon wait-time thresholds (`waits`) set per queue; alerts routed to a human (§8).
- [ ] Autoscaling/ceilings: `maxProcesses` per supervisor × workers fits your DB connection and memory budget.

### 2.2 Build and release
- [ ] Images pinned to a version tag (the Dockerfile comment already recommends it), not a floating tag.
- [ ] `composer install --no-dev --optimize-autoloader` and frontend assets (if any are served from this repo) built in the image.
- [ ] **Migrations run once per release**, by one resource only (`AUTORUN_ENABLED=true` on exactly one container, per the Dockerfile note). Verify the others do not auto-migrate.
- [ ] Release procedure written down: build → migrate → swap web → terminate Horizon → verify. Includes who approves and how to abort.
- [ ] Rollback procedure tested on staging: previous image + what to do if a migration already ran. Prefer **backward-compatible migrations** (expand → deploy → contract).
- [ ] Zero-downtime deploy verified (no 502 during rollout; in-flight runs survive a Horizon restart: confirm jobs are retried/resumed, see §6.3).
- [ ] Maintenance mode procedure tested (`APP_MAINTENANCE_DRIVER` supports multi-container setups; the default `file` driver does not coordinate across containers, use the cache driver).

### 2.3 Network and edge
- [ ] DNS for API, websocket, and (if separate) frontend/app domains. TTLs lowered 24h before launch.
- [ ] TLS everywhere, auto-renewal verified, HSTS enabled after you are sure HTTPS works.
- [ ] Only 80/443 public. Postgres, Redis, Horizon, Reverb internal ports are **not** reachable from the internet.
- [ ] CDN/WAF in front (e.g. Cloudflare): bot management, basic WAF rules, DDoS protection, caching rules that never cache authenticated API responses.
- [ ] Real client IP is trusted correctly (trusted proxies configured) so rate limits (`->by($request->ip())`) and audit logs do not all see the proxy IP. **VERIFY**: this silently breaks every per-IP limiter if wrong.
- [ ] Request size limits at the proxy match `TRIGGERS_MAX_WEBHOOK_BODY_KB` and upload limits; request timeouts match streaming/long AI responses (SSE/websocket idle timeouts).
- [ ] Outbound egress policy: workflows call arbitrary URLs. Prefer an egress proxy or network policy that blocks internal ranges and cloud metadata (§3.5).

### 2.4 Capacity
- [ ] Starting size chosen from a load test (§9), with headroom of at least 2× expected beta peak.
- [ ] Disk, memory, CPU and connection alerts set (§8).
- [ ] Cost estimate written down: infra + AI per active workspace per month.

---

## 3. Security (BLOCKER)

### 3.1 Authentication and sessions
- [ ] Rate limiters exist for auth, public API, trigger hooks, assistant hooks, builder, referrals, page submissions (`AppServiceProvider`). Confirm each is **actually applied** to its routes (`php artisan route:list` and check middleware) and the limits are sane for production.
- [ ] Brute-force protection on login, password reset, 2FA, email verification and invitation-accept endpoints.
- [ ] Account enumeration avoided (same response for known/unknown email on reset/login).
- [ ] Password policy, breached-password check (optional), session invalidation on password change and 2FA reset.
- [ ] Email verification required before using costly features.

### 3.2 Authorization and tenant isolation
- [ ] Every route has an authorization check (policy/gate/permission). Review `php artisan route:list` for any route that only has `auth` and loads a model by ID.
- [ ] **Two-tenant test:** create workspace A and B with real data (workflows, runs, agents, memories, secrets, knowledge sources, connectors, API keys, pages, templates). As A, request B's IDs on every endpoint (read, update, delete, run, export). Expect 403/404 each time. Automate it as a Pest test and keep it forever.
- [ ] Global scopes / explicit workspace scoping on queries; embeddings/vector search filter by workspace **inside** the query, not after.
- [ ] Workspace roles and `Permission` enum reviewed: least privilege defaults, owner-only for billing, member removal, API key creation.
- [ ] Invitation flow cannot be abused to join other workspaces or escalate role.
- [ ] API keys: hashed at rest, shown once, scoped, revocable, last-used tracked, rate-limited.
- [ ] Public pages (`PageTemplate`, page submissions, unlock): confirm no private data leaks through public page endpoints; unlock codes rate-limited (`page-unlock`).
- [ ] Super Admin (`docs/SUPER_ADMIN_PLAN.md`): `SUPER_ADMIN_ENABLED` is **false** on every deploy that should not expose it; on the admin deploy set `SUPER_ADMIN_ALLOWED_IPS` / `SUPER_ADMIN_HOST` / `SUPER_ADMIN_ALLOWED_ORIGINS`, create the first owner with `php artisan super-admin:create you@example.com --role=owner`, finish password change + two-factor enrolment, and check `super-admin:audit-log` records what you did.

### 3.3 Admin and operational dashboards
- [ ] Spend guard: set `MODEL_DAILY_BUDGET_USD` and `MODEL_WORKSPACE_DAILY_USD`, set `SUPER_ADMIN_ALERT_EMAILS` to a mailbox someone reads, and confirm the scheduler runs `model-usage:rollup` and `model-spend:check` hourly.
- [ ] Audit log: run `php artisan super-admin:audit-verify` and write down the `head` it prints somewhere outside the database; re-check with `--expect-hash=<head>` now and then. Know the incident commands: `super-admin:lockdown` (every session out) and the `POST /models/providers/{provider}/pause` kill switch for a leaked AI key.
- [ ] Models: run `php artisan setup:catalog:sync --only=model-prices` (or `model-prices:import-config`), then `php artisan model-catalog:doctor` and clear every error; price every model you pay for (`Super Admin → Models → Margins` shows which are unpriced or losing money).
- [ ] Horizon gate (`viewHorizon`) restricted to `SUPER_ADMIN_DASHBOARD_IPS`: test an allowed and a disallowed address in production.
- [ ] Pulse dashboard gate (`viewPulse`) restricted to `SUPER_ADMIN_DASHBOARD_IPS`: test an allowed address gets in while an unauthenticated and a non-listed request return 403.
- [ ] Any other admin UI/route (artifacts, debug, telescope-like tools, `/docs`, OpenAPI, Boost/MCP endpoints) is disabled or gated in production. `laravel/boost` is a dev dependency: confirm it is **not installed** in the production image and its MCP routes are not exposed.
- [ ] No debug routes, no test/seed users, no default credentials.

### 3.4 Secrets and encryption
- [ ] BYOK keys, connector tokens, `Secret` values and OAuth tokens are encrypted at rest (casts in `app/Casts`). **VERIFY** by reading raw DB rows in staging.
- [ ] Secrets never appear in: logs, run/node outputs, AI prompts, exception reports, error tracker payloads, API responses, broadcast events. Add log scrubbing and error-tracker "before send" filters.
- [ ] Run history is visible to teammates: confirm secrets/credentials are masked in `runs`, `node_runs`, `trigger_events` payload snippets.
- [ ] Secret rotation runbook: for `APP_KEY`, Passport keys, Stripe, each AI provider, OAuth client secrets, Reverb, DB/Redis passwords. Practice one rotation on staging.
- [ ] Use a secrets manager; limit who can read production env vars; enable audit logs on the hosting account.

### 3.5 Outbound requests and SSRF
- [ ] Every node/feature that fetches a user-supplied URL (HTTP/Call API node, webhook delivery, knowledge-base URL sync, skill sources, page assets, connectors) goes through one hardened HTTP client: blocks private/loopback/link-local ranges and cloud metadata IPs, re-validates after redirects and DNS resolution (anti DNS rebinding), enforces timeouts, response size caps and allowed schemes.
- [ ] Redirect limits and content-type checks for downloads.

### 3.6 Code execution and agent safety
- [ ] `RunCodeNode`/E2B sandbox: network egress rules, CPU/memory/time limits, no access to platform secrets or metadata, per-workspace quotas. If not hardened, **disable it for launch**.
- [ ] Prompt-injection posture: content from emails, web pages, files and tool results is treated as untrusted data. Risky actions (send, delete, pay, share, external writes) require approval via the `ActionGate`/approvals system; verify `DeclaresEffect` on every node that mutates the world. A node without it defaults to "ask": keep that default.
- [ ] Autonomy defaults for new agents are conservative (ask before write/destructive).
- [ ] Per-run limits: max steps, max tool calls, max sub-agent depth/fan-out (`InvokeAgentTool::MAX_CONCURRENT`), max tokens, max wall time, loop-node iteration cap.
- [ ] Output handling: AI-generated HTML/markdown rendered on pages is sanitized (XSS).

### 3.7 Webhooks and inbound surfaces
- [ ] Trigger webhooks: unguessable URLs/tokens, optional signature verification, replay protection, body size cap (`TRIGGERS_MAX_WEBHOOK_BODY_KB`), rate limit (`TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE`).
- [ ] Stripe webhook: signature verified with `STRIPE_WEBHOOK_SECRET`, excluded from CSRF, idempotent (`processed_webhook_events`).
- [ ] Assistant inbound email/Slack/SMS endpoints: signature/token checked, sender authenticated (`ASSISTANT_EMAIL_REQUIRE_AUTH=true`), impersonation (spoofed From) cannot trigger actions on someone's behalf.

### 3.8 HTTP hardening
- [ ] Security headers (HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy` for any HTML you serve, frame options for pages).
- [ ] CORS restricted (§1.5). CSRF handled correctly for cookie-based flows; token auth for API.
- [ ] Error responses never leak stack traces, SQL, file paths or internal hostnames.
- [ ] File uploads: type/size validation, virus scanning if you accept arbitrary files, stored outside web root, served with safe content types.

### 3.9 Supply chain and repo
- [ ] `composer audit` and `npm audit` clean or reviewed; lockfiles committed.
- [ ] Run a secret scan over the repository **and history** (including `docs/`, `bruno/`, `.env.example`, `.mcp.json`, `opencode.json`, `.kiro`, `.factory`, `.amp`, `.agents`, `.claude` tool configs). Rotate anything found.
- [ ] Production image has no dev dependencies, no `.git`, no test fixtures, no tool/agent configuration directories you do not need.
- [ ] Enable dependency update automation (Dependabot/Renovate) and branch protection on the default branch.
- [ ] `laravel/ai` is v0.x: pin to an exact version and read release notes before every upgrade.

### 3.10 Security process
- [ ] `security.txt` / security contact address, and a one-page "leaked key / suspected breach" runbook (§13).
- [ ] Decide on a bug bounty or responsible disclosure stance.
- [ ] Optional but recommended: an external penetration test or at minimum an OWASP ZAP/Burp pass of the staging API before public launch.

---

## 4. Billing and money (BLOCKER)

### 4.1 Stripe configuration
- [ ] Live-mode `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` set. Test keys do not exist in production env.
- [ ] Live products/prices created for every plan and interval; IDs set via `STRIPE_PLAN_<PLAN>_*_PRODUCT_ID/PRICE_ID` (starter, pro, … monthly/quarterly/yearly/lifetime) and match `PlansDataset` and `plans` rows.
- [ ] `PlansDataset` run in production; prices, credits and limits in the DB are the intended launch numbers.
- [ ] Webhook endpoint registered in live mode with only the events you handle; failed-delivery email alerts on.
- [ ] Customer Portal configured (payment methods, invoices, cancel/pause rules).
- [ ] Tax: Stripe Tax or manual VAT/GST/sales tax decision made; business address, tax IDs and invoice footer set; `CASHIER_CURRENCY` and locale confirmed.
- [ ] Radar rules / 3DS (SCA) behavior understood; `IncompletePayment` flows handled in the UI.
- [ ] Billing kill switches known: `BILLING_INTERVAL_*_ENABLED` flags (e.g. disable lifetime sales instantly).

### 4.2 Live-mode dry run (do this with your own card, then refund)
- [ ] Sign up → start trial → add payment method → subscribe monthly.
- [ ] Credits granted correctly, usage periods created, balance visible.
- [ ] Consume credits through real runs; check `credit_transactions` is append-only and sums match the displayed balance.
- [ ] Upgrade (proration), downgrade, change interval, cancel, resume.
- [ ] Failed payment (use a card that fails on renewal), dunning emails, grace period, access after failure, recovery.
- [ ] Trial expiry (`billing:expire-trials`), trial-ending email (`billing:notify-trial-ending`), plan-grant expiry (`billing:expire-plan-grants`).
- [ ] Overage invoicing (`billing:invoice-overage`) end to end, including idempotency if the command runs twice.
- [ ] Lifetime plan purchase and grant, if enabled.
- [ ] Refund and chargeback: what happens to credits and access.
- [ ] Replay each webhook event twice and out of order: no duplicate credits, no negative states.
- [ ] Invoices/receipts render with correct legal details and currency.

### 4.3 Cost protection
- [ ] **Credit price vs real cost:** compute cost per run for the main flows (LLM tokens + sandbox + email/SMS + storage) and confirm margin per plan, including the heaviest 5% of users.
- [ ] Per-workspace caps: credits, concurrent runs, runs/minute, tokens/run, steps/run; behavior when credits hit zero (stop gracefully, notify, never go negative).
- [ ] Free-tier caps are tight and enforced server-side.
- [ ] BYOK path: runs on a user's key do not consume platform credits (or consume the intended fraction); a revoked/invalid user key fails with a clear message, and `ai-credentials:check` flags it.
- [ ] Cost tagging: every LLM/sandbox call is attributable to workspace, agent/workflow, feature. A daily spend report exists.
- [ ] Spend alerts: provider-side limits (§1.7) **and** an internal alert when daily spend > X× trailing average.
- [ ] Referral rewards (`referrals:*`) cannot be farmed: self-referral, same card/device/IP, disposable emails. Review rules with `SimulateReferralCommand`.

---

## 5. Observability and alerting (BLOCKER for beta)

- [ ] **Error tracking** (Sentry/Flare/Nightwatch) installed; send a deliberate exception from production; PII/secrets scrubbed; release version tagged.
- [ ] **Uptime monitoring** from outside your network: API `/up`, a real authenticated endpoint, websocket handshake, marketing site, status page.
- [ ] **Logs**: centralized, searchable, retained (≥30 days), each line carries request ID, user ID, workspace ID, run ID where relevant.
- [ ] **Pulse** installed, gated (§3.3), recorders sampled appropriately, Redis ingest if volume is high.
- [ ] **Horizon** metrics: `horizon:snapshot` scheduled (confirm it is in the schedule; it is not in `routes/console.php`; add if missing), long-wait notifications routed to a real channel (Slack/email) instead of the commented-out placeholders in `HorizonServiceProvider`.
- [ ] **Admin alerts** (`config/admin_alerts.php`) pointed at a channel someone reads; `TestAdminAlertCommand` fired in production.
- [ ] **Scheduler heartbeat:** a dead-man's-switch (e.g. a scheduled ping to a monitoring URL every minute). Alert if it stops. `Dockerfile.scheduler` has a health check but nothing alerts on it by itself.
- [ ] **Alerts defined** (with owner and severity):
  - web 5xx rate, p95 latency
  - failed jobs rate, failed-jobs table growth
  - queue wait time per queue, queue depth, oldest job age
  - stuck runs / stuck triggers (`triggers:retry-stuck`, `FailStuckAgentTurnsJob`, `ExpireStaleWaitsJob` should be keeping these near zero: alert when they find more than N)
  - connector health failures (`connectors:check-health`) and AI credential failures
  - Stripe webhook failures and `processed_webhook_events` backlog
  - DB: CPU, connections, replication lag, disk, slow queries, lock waits
  - Redis: memory, evictions, connections
  - disk and memory on every container
  - daily AI spend anomaly (§4.3)
  - certificate expiry, domain expiry
- [ ] **Run traceability:** from a user's complaint you can find: the run, each node's input/output (masked), the model, tokens, cost, latency and the error, in under 2 minutes.
- [ ] **Business dashboard** (admin): sign-ups, activations, runs/day, success rate, credits consumed, revenue, top errors, top workspaces by cost.
- [ ] **Status page** (public) with a manual incident post procedure.

---

## 6. Reliability, data and correctness (BLOCKER)

### 6.1 Backups and recovery
- [ ] Automated Postgres backups, off-site copy, encryption, retention policy.
- [ ] **A restore has actually been performed** into a fresh instance and the app booted against it. Record the time it took.
- [ ] RPO (acceptable data loss) and RTO (acceptable downtime) written down and achievable with the above.
- [ ] Redis loss plan: what is lost (queued jobs, cache, locks, Horizon metrics) and how the system recovers (stuck-run reapers rebuild state; verify).
- [ ] Object storage versioning/backup for user files.
- [ ] Take a manual full backup immediately before launch day.

### 6.2 Migrations and data
- [ ] `php artisan migrate --force` on a **copy of production-sized data** completes without long locks. Large-table changes use online patterns.
- [ ] Seeders to run in production are listed and **idempotent**: `PlansDataset`, `NodeCategoriesDataset`, `AppNodeCategoriesDataset`, `ConnectorsDataset`, `ModelCatalogDataset`, `TriggerPresetsDataset`, `AgentTemplatesDataset`, `PageTemplatesDataset`, `ReferralProgramDataset`. Running twice must not duplicate or overwrite user-modified rows. `DatabaseSeeder` must **not** create demo users in production.
- [ ] Indexes reviewed for hot paths: runs by workspace/status/created_at, node_runs by run, trigger_events by trigger/status/created_at, agent_messages by session, credit_transactions by workspace, document_embeddings (vector index), processed_webhook_events (unique key). Run `EXPLAIN` on the main list endpoints with realistic row counts.
- [ ] Foreign keys and cascades checked: deleting a workspace/user removes or anonymizes dependent data deliberately.

### 6.3 Execution engine behavior
- [ ] Kill a Horizon worker mid-run on staging: the run resumes, retries or fails cleanly and visibly; no run stays "running" forever (verify the reapers: `ExpireStaleWaitsJob`, `FailStuckAgentTurnsJob`, `triggers:retry-stuck`).
- [ ] Kill Redis briefly, kill the DB briefly, kill the AI provider (block egress) and confirm graceful degradation and recovery.
- [ ] Idempotency: a retried job never double-sends an email, double-charges, double-posts, or double-debits credits. Confirm node-by-node for side-effecting nodes.
- [ ] Cancellation works for runs and agent turns; cancelled runs stop spending.
- [ ] Schedule/trigger catch-up behavior after downtime (`TRIGGERS_SCHEDULE_CATCH_UP_MINUTES`) is what you want: no thundering herd of missed runs after an outage.
- [ ] Concurrency limits: one workspace cannot saturate shared queues. Verify per-workspace throttling or at least caps on concurrent runs.
- [ ] Time zones/DST for schedules and briefings tested.
- [ ] Long-running AI calls: timeouts at every layer are consistent (proxy < PHP < job timeout < `retry_after`).

### 6.4 Retention and growth
- [ ] Pruning on: `triggers:prune-events` (`TRIGGERS_EVENTS_RETENTION_DAYS`), `referrals:prune-visits`, `workflow-builder:archive-idle`. Add retention for `runs`, `node_runs`, `agent_messages`, failed jobs, Pulse tables, Horizon completed jobs, notifications, and old versions. Define defaults per plan.
- [ ] Large payloads (run inputs/outputs, files) stored with size caps or in object storage rather than unbounded JSON columns.
- [ ] Capacity forecast: rows/day × retention → storage in 3 and 12 months.

---

## 7. Product readiness

### 7.1 First-run experience
- [ ] Brand-new account → first successful automation in under 5 minutes, on desktop and phone, in an incognito window, with no admin help.
- [ ] Onboarding flow (`Onboarding`) tested for every path (email signup, Google, GitHub, invited member).
- [ ] Empty states everywhere give a next action. Sample/starter templates one click away.
- [ ] Credit balance, plan and limits visible; cost estimate before running expensive things.

### 7.2 Templates and content
- [ ] 10-20 launch templates, each aimed at a concrete outcome, **tested end to end on a clean workspace in production**.
- [ ] Templates contain no workspace IDs, connection IDs, secrets, emails or personal data; "use template" remaps connections and asks for missing ones.
- [ ] A CI/pest check validates every seeded template against the current node registry and `ConfigSchemaValidator`, plus a dry run (§12).
- [ ] Installing a template creates a **draft**, never an auto-activated trigger/schedule.
- [ ] Seeders are idempotent by stable slug (§6.2). Decide what happens to user copies when a template changes (normally nothing).
- [ ] Agent templates and page templates reviewed for brand, tone, accuracy and safe default autonomy.

### 7.3 Nodes and connectors
- [ ] Launch node set decided (depth over breadth): list the nodes that ship and mark the rest as hidden/"beta" behind a flag.
- [ ] Every shipped node has: valid `configSchema()`, output schema, `DeclaresEffect`, clear error messages, timeouts, pagination caps, retry/idempotency stance, tests with faked HTTP, tested once against the **real** API.
- [ ] Node `type()` strings are **frozen** from launch day (saved workflows reference them). Deprecate by adding new types, never rename.
- [ ] Per-node kill switch exists (so you can disable a broken or abused node globally without a deploy).
- [ ] Connector flows: connect, test, refresh (`connectors:refresh-expiring`), revoke, reconnect after revocation mid-run; health surfaced in the UI (`connectors:check-health`).
- [ ] The AI workflow builder knows the launch node set and builds working flows for the top 10 prompts (add them as evals, §10.4).

### 7.4 Debugging and trust
- [ ] A failed run shows: which node, masked input/output, plain-language reason, a suggested fix, one-click retry/"fix with AI".
- [ ] Human approvals (`workflow_approvals`, agent action approvals) are discoverable and notify the right person; expiries behave (`agents:expire-actions`, `assistant:expire-actions`).
- [ ] Notifications: failures, low credits, expiring connections, trial ending. Sensible defaults, easy opt-out, no spam loops (rate-limit repeated failure alerts).
- [ ] Clear limit/upsell messages; every error says what happened and what to do next.

### 7.5 Compatibility
- [ ] Frontend ↔ API contract verified on production URLs (CORS, cookies, websocket, file upload/download, SSE streaming through the proxy).
- [ ] API documented (Bruno collection in `bruno/` kept in sync) for anything customers can call.
- [ ] API versioning stance stated; breaking changes policy written.

---

## 8. Legal, compliance and policy (BLOCKER for public launch)

- [ ] Terms of Service, Privacy Policy, Acceptable Use Policy (spam, scraping, illegal use, automated mass messaging), Cookie policy if applicable. Reviewed by a lawyer or a reputable template service appropriate to your jurisdictions.
- [ ] Sub-processor list (hosting, DB, Stripe, email, every AI provider, error tracking, analytics, Twilio/Slack/E2B) published; DPA template available for business customers.
- [ ] Statement on AI data use: what is sent to providers, retention, no-training status, BYOK implications.
- [ ] Consent/cookie banner for EU/UK visitors if you use analytics/marketing cookies.
- [ ] Data subject requests: account deletion and data export work end to end (including embeddings, memories, run history, backups retention note). Test with a real account.
- [ ] Email compliance: unsubscribe in marketing mail, physical address (CAN-SPAM), consent for assistant-sent outreach on behalf of users (users are responsible; make it explicit in terms).
- [ ] SMS compliance if Twilio ships (opt-in/out, registration).
- [ ] Provider terms: Google API Services User Data Policy / Limited Use, Slack, GitHub, Stripe, OpenAI/Anthropic usage policies. Verify your use (reading mail with the assistant, automated actions) is permitted and disclosed in OAuth consent screens.
- [ ] Business entity, bank account, invoicing details, tax registration in place.
- [ ] Age restriction statement (e.g. 18+/16+).
- [ ] Records: audit log retention for security events; breach notification procedure and timelines (e.g. 72 hours under GDPR).

---

## 9. Testing before real users (BLOCKER)

### 9.1 Automated
- [ ] Full Pest suite passes on a clean checkout with the production-like config (Postgres + Redis, not sqlite) at least once: `php artisan test --compact`.
- [ ] Pint clean: `vendor/bin/pint --format agent`.
- [ ] Static analysis (Larastan/PHPStan) at a baseline level, not necessarily max.
- [ ] Tests exist and pass for: tenant isolation, billing/credits ledger and webhook idempotency, trigger firing/retry, approval gates, rate limits on auth, every node (faked HTTP), template validation, scheduler commands (idempotent when run twice).

### 9.2 Staging rehearsal
- [ ] Staging mirrors production topology (four services, Postgres, Redis, S3, Reverb, real SMTP, Stripe test mode, AI keys with low limits).
- [ ] Full dress rehearsal of the deploy, migration, rollback and restore procedures.
- [ ] Walk the entire user journey with fresh accounts: sign-up, verify, onboard, connect, template, run, schedule, webhook trigger, agent, assistant, billing, invite teammate, delete account.

### 9.3 Load and failure
- [ ] Load test the main paths (sign-up/login, list endpoints with realistic data, start run, webhook ingestion burst, websocket fan-out, concurrent agent turns). Record limits and the first bottleneck.
- [ ] Seed realistic volume (e.g. 100k runs, 1M node_runs, large embeddings) and check slow queries.
- [ ] Soak test 24h with scheduled triggers and agents running; watch memory growth in Horizon workers and Reverb.
- [ ] Chaos checks: kill each service one at a time and confirm detection (alerts fire) and recovery.

### 9.4 Abuse test
- [ ] Hostile-tenant exercise: infinite loop workflow, huge payloads, thousands of triggers per minute, prompt-injection email to the assistant, SSRF attempts, oversized uploads, mass sign-ups from one IP. Confirm limits hold and nothing starves other tenants.
- [ ] Prompt-injection and jailbreak cases included in evals (§10.4).

---

## 10. AI quality and safety

- [ ] **Eval set** for each major AI surface (workflow builder, agents, assistant, node-config agent, skill drafting): fixed prompts with pass/fail criteria, run before every prompt/model change. Even 30-50 cases is a big step up from none.
- [ ] Model versions pinned; new model adoption goes through the evals first.
- [ ] Token/cost budgets per feature; cheaper models for classification/extraction, stronger for planning; prompt caching used where available.
- [ ] Guardrails: tool-argument validation, max loops, "ask the user" path, refusal for disallowed actions, content moderation on public-facing inputs.
- [ ] Memory (`agent_memories`) is viewable, editable and deletable by users; expiry/consolidation policy exists.
- [ ] Knowledge base: chunking/retrieval tested, per-workspace isolation verified, re-embed procedure documented (needed if `AI_EMBEDDINGS_PROVIDER` ever changes).
- [ ] In-product feedback (thumbs up/down + trace attached) captured from day one.
- [ ] Transparency: AI-generated content/actions labeled where users would expect it; assistant emails sent on a user's behalf clearly authorized.

---

## 11. Support and operations readiness

- [ ] Support channel live (email + in-app link), monitored hours stated, SLA you can keep.
- [ ] Help center with the top 15 articles: connect each integration, how credits work, billing/cancel, why a run failed, security/privacy FAQ.
- [ ] Bug-report flow attaches run ID, workspace ID and masked trace automatically.
- [ ] Internal admin tools: look up a user/workspace, impersonate-with-audit (if you build it), grant credits, extend trial (`ExpirePlanGrants`, referral reward commands exist), suspend/unsuspend a workspace, disable a node/connector, replay a webhook, refund. Test each as the on-call person.
- [ ] **Kill switches documented and rehearsed:** pause sign-ups, pause all scheduled triggers (`TRIGGERS_NODES_ENABLED`), disable a node type, disable an AI provider/model route, disable a billing interval (`BILLING_INTERVAL_*_ENABLED`), put the API in maintenance mode.
- [ ] **Runbooks** (one page each): stuck queue / Horizon down; scheduler not running; provider outage or rate-limit storm; Stripe webhook backlog; Redis down; DB failover/restore; runaway spend; leaked secret; abusive tenant; failed deploy/rollback.
- [ ] On-call rota, escalation contacts, and incident communication template (status page + email).
- [ ] Shared access to critical accounts (DNS, hosting, Stripe, AI providers, Google/Slack developer consoles, domain registrar) with MFA, in a password manager. No single point of human failure.

---

## 12. Engineering process (before you scale the team or the codebase)

- [ ] **CI** (none exists today): GitHub Actions workflow running `composer install`, Pint, tests (Postgres + Redis services), `composer audit`, on every PR and push to the default branch.
- [ ] Branch protection: required checks, required review, no direct pushes to the default branch.
- [ ] CD: automatic deploy to staging on merge; production deploy gated/manual with the release procedure (§2.2).
- [ ] Staging and production use separate credentials for everything.
- [ ] Feature flags (config flags or Laravel Pennant) for risky/new features and per-workspace rollout.
- [ ] Dependabot/Renovate on Composer and npm.
- [ ] Docs kept current: `docs/*_PLAN.md`, `NODES_CATALOG.md`, a "how to add a node / template / agent / connector" guide, an architecture overview, and ADRs for big decisions.
- [ ] Technical-debt list kept (recent migration consolidation, `LegacyTriggersCommand`, any stubs and TODOs). `grep -rn "TODO\|FIXME" app` triaged before launch.

---

## 13. Launch day plan

### T-7 days
- [ ] Code freeze for non-fixes; only launch-critical changes merge.
- [ ] Lower DNS TTLs; confirm certificates; confirm domain auto-renew.
- [ ] Final staging rehearsal of deploy + rollback + restore.
- [ ] Support inbox and status page live; rota published.

### T-1 day
- [ ] Verify provider spend limits (§1.7), Stripe live mode (§4), alert routing (§5), backups ran last night and restore point exists (§6.1).
- [ ] Run seeders in production (idempotent set, §6.2) and verify plan/template/node/connector data visually.
- [ ] Create the first super admin (`super-admin:create --role=owner`) and test Super Admin access (admin API, Horizon, Pulse from an allowed address) and denial for a normal user token, a disabled admin and a disallowed address.
- [ ] Smoke test checklist scripted: sign-up, verify email, login, create workflow from template, run, webhook trigger, agent turn, billing page, logout.
- [ ] Remove any test data/accounts created during rehearsal.

### Launch day
- [ ] Take a full backup. Note the release version/commit.
- [ ] Deploy per procedure; run migrations once; confirm all four services healthy; confirm Horizon supervisors are running and the scheduler is ticking.
- [ ] Run the smoke test on production with a real new account (incognito, phone).
- [ ] Enable access in stages: staff → 5-10 friendly users → waitlist batch → public. Wait and watch between stages.
- [ ] During the first hours watch: errors, failed jobs, queue waits, run success rate, provider rate limits/spend, Stripe events, sign-up → first-run funnel, support inbox.
- [ ] Have the kill switches (§11) one command away; decide in advance the thresholds that make you pause sign-ups (e.g. error rate > 5%, spend > X/hour, failed-run rate > Y%).

### First week
- [ ] Daily: errors, spend vs revenue, failed runs by node/connector, top support themes, queue health, slow queries.
- [ ] Talk to the first 10 users (calls or short surveys); record where they got stuck.
- [ ] Fix the top 3 onboarding/debugging pains before adding features.
- [ ] Review rate limits and plan limits against real behavior; adjust.
- [ ] After 7 days: review logs for secrets leakage, review audit logs for admin actions, review the abuse list.

---

## 14. Marketing and growth basics

- [ ] Landing page with real use cases, pricing, security/privacy summary, and a clear "start free / join waitlist" action.
- [ ] Template gallery pages indexable for SEO ("Gmail to Slack automation", etc.); sitemap, canonical URLs, social cards.
- [ ] Product analytics (privacy-respecting) on the activation funnel: sign-up → verified → connected an app → first run → first success → second-day return.
- [ ] Changelog and public roadmap/feedback board.
- [ ] Listings where users look (integration directories, marketplaces, Product Hunt) once the product is stable; some require provider app review (Google, Slack).
- [ ] Referral program (`referrals:*`) tested with real rewards and abuse rules before promoting it.
- [ ] Brand/domain protection: related domains, social handles.

---

## 15. After launch: recurring habits

**Daily (first month)**: errors and failed jobs · queue waits · spend vs revenue · support inbox · sign-up funnel.
**Weekly**: top failing nodes/connectors · cost per active workspace · retention cohorts · slow query review · dependency/security advisories · template and node usage (retire unused, invest in used).
**Monthly**: restore test of a backup · rotate/validate one secret · review plan pricing and margins · provider deprecation notices and model updates (run evals first) · review access to production accounts · review retention and storage growth · review incident log and runbooks.
**Quarterly**: load test again · pen test or security review · legal pages review · disaster-recovery drill · roadmap vs metrics ("stop or double down").

---

## Go / No-Go gate

Do **not** open to external users unless every item below is true:

1. Production config is complete (§1), all four services run healthy (§2), and rollback was rehearsed.
2. Tenant isolation test passes; Horizon **and** Pulse are admin-gated; SSRF and code-execution risks are mitigated or the features are disabled (§3).
3. Stripe is in live mode and the full billing dry run passed; provider spend limits are set (§4).
4. Error tracking, uptime checks, alerts and the scheduler heartbeat are live and someone will see them (§5).
5. A backup restore has been performed; stuck runs recover on their own (§6).
6. Launch templates and nodes are tested end to end in production (§7).
7. Legal pages, sub-processor list and account deletion/export are in place (§8).
8. CI is green and the full test suite passed against Postgres + Redis (§9, §12).
9. Kill switches and runbooks exist and the on-call person knows where they are (§11).
10. You have a written pause-signups threshold and a rollback plan for launch day (§13).
