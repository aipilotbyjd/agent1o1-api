<p align="center"><strong>Agent1o1 API</strong></p>

<p align="center">A Gumloop-style, node-based workflow & AI-agent automation platform — backend API only.</p>

## About

Agent1o1 is a SaaS backend that lets users build and run AI-powered automations. It combines two engines behind one REST API:

- **Workflow engine** — DAGs of typed nodes (triggers, transforms, AI, integrations) executed via queued jobs, with type checking at save time, loop/batch fan-out, human-in-the-loop checkpoints, and error-shield-based recovery.
- **Agent layer** — conversational, tool-driven AI agents (built on [Laravel AI](https://laravel.com/docs/ai)) that can call Connectors, Workflows, and Skills as tools, with every tool call metered and logged like a node run.

Multi-tenancy is workspace-based, with two API surfaces sharing the same underlying `Actions/` business logic:

- **`/api/internal`** — session-authenticated (Sanctum), used by the first-party React canvas/editor. Includes editor-only endpoints (node placement, autosave, etc.).
- **`/api/v1`** — API-key-authenticated, versioned, external-developer-facing. Narrower: run/list/inspect only (Workflows, Runs, Agents, Artifacts, Skills).

Usage is metered via a credit ledger (`CreditTransaction`), billed through [Laravel Cashier](https://laravel.com/docs/billing) + Stripe (subscriptions, credit packs, lifetime plans, dunning on failed payments).

See `docs/` for the full architecture and phased build plans (`PLAN.md`, `STRUCTURE.md`, `WORKFLOWS_PLAN.md`, `AGENTS_PLAN.md`, `AUTH_PLAN.md`, `TRIGGERS_PLAN.md`, `WORKSPACE_PLAN.md`, `ONBOARDING_PLAN.md`, `NODES_CATALOG.md`).

## Tech Stack

- **PHP 8.5** / **Laravel 13**
- [Laravel AI](https://laravel.com/docs/ai) — LLM orchestration & agent tool-calling
- [Laravel Cashier](https://laravel.com/docs/billing) — Stripe subscriptions & billing
- [Laravel Horizon](https://laravel.com/docs/horizon) — queue monitoring (Redis-backed)
- [Laravel Passport](https://laravel.com/docs/passport) — OAuth2 API authentication
- [Laravel Reverb](https://laravel.com/docs/reverb) — real-time WebSocket broadcasting
- [Laravel Socialite](https://laravel.com/docs/socialite) — Google/GitHub social login
- [Laravel Pulse](https://laravel.com/docs/pulse) — application monitoring
- [Pest](https://pestphp.com) — testing

## Getting Started

Requires PHP 8.5, Composer, Node.js, and Redis. The app is served locally via [Laravel Herd](https://herd.laravel.com) at `https://agent1o1-api.test`.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Fill in `.env` with real values for Stripe (keys, webhook secret, plan/price IDs), Passport OAuth client, Google/GitHub OAuth apps, Reverb, and your AI provider key(s) (`OPENAI_API_KEY` / `ANTHROPIC_API_KEY`).

### Local development

```bash
composer dev
```

Runs the app server, queue worker, log tailer (`pail`), and Vite dev server concurrently.

### Testing

```bash
composer test
# or a specific suite/filter:
php artisan test --compact --filter=SomeTest
```

## Agentic Development

This project has [Laravel Boost](https://laravel.com/docs/ai) installed, giving AI coding agents (Claude Code, Cursor, GitHub Copilot, etc.) direct tools for Artisan, database inspection, and version-aware documentation search. See `CLAUDE.md` for project-specific agent guidelines and available skills.

## License

Proprietary — all rights reserved.
