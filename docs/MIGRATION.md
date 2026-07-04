# Migration: agent1o1-api-jd → agent1o1-api

Moving the full application from **`agent1o1-api-jd`** (the mature app, currently
deployed on Dokku at `dokku@168.144.113.26`) into **`agent1o1-api`** (this repo,
already wired into Coolify with 4 resources + Postgres/pgvector + Redis).

Keep this repo's deployment assets during the move: `Dockerfile.web`,
`Dockerfile.horizon`, `Dockerfile.reverb`, `Dockerfile.scheduler`,
`.dockerignore`, `docs/PLAN.md`.

## Why it fits

- jd already uses **Horizon, Reverb, predis** at the same versions as here.
- jd needs **pgvector** (`document_embeddings`) — the Coolify Postgres is
  `pgvector/pgvector:pg18`, so that's covered.
- Coolify already deploys from `aipilotbyjd/agent1o1-api`, so the app should
  land in this repo rather than repointing infra.

## Scale (jd vs here)

| | jd (old) | here (new) |
|---|---|---|
| app PHP files | 515 | 5 |
| migrations | 77 | 3 |
| models | 62 | 1 |
| controllers | 74 | 1 |
| api.php route lines | 291 | — |

---

## Stage 1 — dependencies & config

- [ ] Add composer packages: `laravel/passport`, `stripe/stripe-php`,
      `laravel/ai`, `laravel/pulse`, `laravel/boost` (dev)
- [ ] Bump `php` constraint `^8.3` → `^8.4` (Dockerfiles already use 8.4)
- [ ] Copy config files: `ai.php`, `billing.php`, `cors.php`, `passport.php`,
      `pulse.php`, `workflow.php`
- [ ] Reconcile `.env.example` (add all new keys — see Env section)
- [ ] Diff jd's `docker/php` (`app.ini`, `opcache.ini`) + required PHP
      extensions against our 4 Dockerfiles; add any missing (`gd`, `zip`,
      `pgsql` for pgvector queries, etc.)

## Stage 2 — application code

- [ ] `app/Engine/` (95) — workflow execution engine (core)
- [ ] `app/Http/Controllers/` (74)
- [ ] `app/Models/` (62)
- [ ] `app/Services/` (43)
- [ ] `app/Agents/` (30) — AI agents
- [ ] `app/Enums/` (17), `app/Jobs/` (10), `app/Events/` (8),
      `app/Listeners/`, `app/Authorization/`, `app/Policies/`,
      `app/Contracts/`, `app/Notifications/`, `app/Observers/`,
      `app/Traits/`, `app/Console/`, `app/Exceptions/`
- [ ] `database/migrations/` — all 77 (workflows, triggers catalog,
      credentials, workspaces/environments, templates, approvals, git-sync,
      `document_embeddings` (pgvector), billing, passport, pulse)
- [ ] `database/factories/`, `database/seeders/`
- [ ] Routes: `api.php` (291 lines), `web.php`, `channels.php`, `console.php`
- [ ] Any `Providers/` beyond the defaults (register app service providers)

## Stage 3 — frontend (only if the app serves UI)

- [ ] `package.json` (Tailwind 4 + Vite 8), `resources/`, `vite.config.*`
- [ ] Wire `npm ci && npm run build` into `Dockerfile.web` (currently absent)

## Stage 4 — Coolify secrets & runtime setup

Set on the relevant apps (mostly **web**; workers need the shared subset):

- [ ] **Passport keys** — set `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`
      as env (preferred for rolling deploys) OR run `passport:keys` at boot
- [ ] **Object storage (required by PLAN.md §7)** — `FILESYSTEM_DISK=s3`,
      `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`,
      `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`
- [ ] **Stripe** — `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`,
      `STRIPE_PRICE_PACK_*`
- [ ] **AI providers** — `AI_PROVIDER`, `GROQ_API_KEY`, `ANTHROPIC_API_KEY`,
      `OPENAI_API_KEY`, `AZURE_OPENAI_*`
- [ ] **Mail** — `MAIL_*`
- [ ] **Pulse** — add auth gate (like Horizon), confirm its migrations run
- [ ] **CORS** — set allowed origins in `config/cors.php` / env

## Stage 5 — deploy & verify

- [ ] Push to `main`; web deploys first (AUTORUN runs the 77 migrations)
- [ ] Watch build for missing PHP extensions / memory (swap is in place)
- [ ] Verify `/up`, then Passport token issue, a workflow run, a queued job
      (Horizon), a broadcast (Reverb), a scheduled task
- [ ] Deploy horizon / reverb / scheduler

---

## Env vars to add (from jd `.env.example`)

```
# Passport
PASSPORT_PASSWORD_CLIENT_ID=
PASSPORT_PASSWORD_CLIENT_SECRET=
PASSPORT_PRIVATE_KEY=
PASSPORT_PUBLIC_KEY=

# Stripe / billing
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_PRICE_PACK_MEDIUM=
STRIPE_PRICE_PACK_LARGE=

# AI providers
AI_PROVIDER=groq
GROQ_API_KEY=
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
AZURE_OPENAI_API_KEY=
AZURE_OPENAI_URL=
AZURE_OPENAI_DEPLOYMENT=
AZURE_OPENAI_API_VERSION=

# Object storage (R2/S3) — required for zero-downtime (ephemeral disks)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=true

# Pulse / Horizon dashboards
HORIZON_DOMAIN=
```

## Notes / risks

- jd's own deploy target was Dokku (single `docker/Dockerfile`); we do **not**
  migrate that — the 4 Coolify Dockerfiles here replace it.
- `document_embeddings` needs the `vector` extension enabled in Postgres
  (`CREATE EXTENSION IF NOT EXISTS vector;`) — verify it's enabled on the
  Coolify pgvector instance.
- 77 migrations on first deploy is a long AUTORUN; set web's health-check
  start period high enough (already 60s — may need more).
- Passport + Pulse each add a dashboard that must be access-gated in prod.
