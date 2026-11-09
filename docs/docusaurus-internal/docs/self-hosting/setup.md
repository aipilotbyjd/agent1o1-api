---
title: Install and set up
description: Run Agent1o1 locally or on your own infrastructure.
sidebar_position: 1
---

# Install and set up

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.5 |
| Composer | 2.x |
| Node.js and npm | current LTS (backend assets). Yarn for the web app |
| PostgreSQL | 16 or newer (tests use SQLite) |
| Redis | Required for queues, cache and Horizon |

## Backend

```bash
git clone <api-repo> agent1o1-api && cd agent1o1-api
composer install
cp .env.example .env
php artisan key:generate
# edit .env: database, redis, mail, AI keys, Stripe, OAuth apps
php artisan migrate
php artisan setup:catalog:sync
php artisan passport:keys
php artisan passport:client --password
npm install --ignore-scripts && npm run build
```

Put the Passport password client id and secret into `PASSPORT_PASSWORD_CLIENT_ID` and `PASSPORT_PASSWORD_CLIENT_SECRET`, then `php artisan config:clear`.

`composer run setup` runs the install, env copy, key generation, migrations, catalog sync and build in one step.

## Setup data {#setup-data}

A migrated database is empty. `setup:catalog:sync` loads plans, node and app categories, trigger presets, connectors, the model catalog and prices, and agent and page templates.

```bash
php artisan setup:catalog:sync            # idempotent; skips unchanged datasets
php artisan setup:catalog:sync --dry-run  # validate without writing
php artisan setup:catalog:sync --only=connectors
php artisan setup:catalog:sync --force
php artisan setup:catalog:status          # non-zero exit if anything is stale
```

Plans, referral program and model prices are created once and then belong to the admin. A sync never overwrites their edits. Nothing runs this on deploy automatically, so run it yourself after `migrate --force`.

## First administrator

```bash
php artisan super-admin:create you@example.com --name="Your Name" --role=owner
```

## Web app

```bash
git clone <web-repo> agent1o1 && cd agent1o1
yarn install
cp .env.example .env     # set VITE_API_URL and VITE_REVERB_*
yarn dev
```

## Local development

`composer dev` runs the HTTP server, queue worker, log tailer and Vite together. With Laravel Herd the API is served on a `.test` domain.

## Tests and style

```bash
composer test
php artisan test --compact --filter=SomeTest
vendor/bin/pint --format agent
```
