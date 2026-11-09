---
title: Architecture
description: Code layout, the Actions layer, the workflow engine and key design decisions.
sidebar_position: 2
---

# Architecture

Laravel 13 on PHP 8.5, with Laravel AI, Cashier, Horizon, Passport, Pulse, Reverb and Socialite. Frontend: React 19, TypeScript, Vite, Tailwind CSS 4, React Flow, TanStack Query, Zustand.

## Backend layout

```
app/
  Actions/       single-purpose use cases (called by every transport)
  Ai/            agents, tools, runtime, observability, usage
  Authorization/ WorkspaceContext
  Broadcasting/  channel gates and reliable broadcasting
  Enums/         typed states, roles, permissions, queues
  Events/ Listeners/ Jobs/   side effects and queued work
  Http/          controllers (Api/Internal, Api/Public, SuperAdmin, Webhooks), requests, resources, middleware
  Models/        Eloquent models grouped by domain
  Nodes/         node classes by category and integration
  Services/      domain services (workflows engine, billing, triggers, assistant ...)
routes/api/{internal,public,super_admin}, routes/webhooks.php
database/setup/  catalog data (plans, connectors, models, templates)
docs/            internal design plans
```

## Key decisions

- **Actions are the shared logic layer.** Public API, Internal API and any future MCP transport call the same Actions.
- **Typed queues.** Every job's queue is a `Queue` enum case.
- **Type-checked graphs** at save time.
- **Durable trigger inbox.** Events are stored before dispatch.
- **Bound-config tool security** for agents.
- **Own role system** (no spatie permission package): `Role` and `Permission` enums with `WorkspaceContext` and a `Gate::before` hook.
- **Passport password grant** for first-party app, bespoke hashed `ApiKey` for the public API.
- **Catalog as data**: nodes, connectors, plans and prices are synced, not hard-coded in migrations.

## Workflow engine

1. `StartWorkflowRunAction` creates a `Run`.
2. `DispatchNextNodesJob` resolves ready nodes.
3. `ExecuteNodeJob` runs one node, writes a `NodeRun`, records cost, and advances the graph.
4. `LoopCoordinator`, `SubWorkflowCoordinator` and approval/wait handling manage fan-out, child runs and pauses.
5. Failures go through step failure handling, retry and error edges.

## Planning documents

The `docs/` folder in the API repo holds design plans (`PLAN.md`, `WORKFLOWS_PLAN.md`, `AGENTS_PLAN.md`, `TRIGGERS_PLAN.md`, `ASSISTANT_PLAN.md`, `AUTH_PLAN.md`, `BYOK_PLAN.md`, `NODES_CATALOG.md`). They go deeper than these user docs.

## Adding a node

1. Create the class under `app/Nodes/<Category>/` implementing `NodeContract` (and `DeclaresOutputSchema`, `DeclaresEffect`, `HasIcon` as needed).
2. Add dropdown option sources if the node has pickers.
3. Register category or connector data in `database/setup/*`.
4. Run `php artisan setup:catalog:sync`.
5. Add Pest tests with faked HTTP.

## Frontend layout

```
src/
  api/        client and per-resource query hooks
  components/ shared UI, layout, forms, icons
  context/    auth, workspace, theme, realtime, brand providers
  pages/      coreapp, identity, settings, welcome, public
  Routes/     page registry, path builders, router
  store/      Zustand stores
```
