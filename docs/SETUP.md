# Project Setup Guide

How to set the project up from scratch on a local machine. Work through the sections in order. This guide grows as the project does: add new steps under the section they belong to.

## Contents

1. [Requirements](#1-requirements)
2. [Install dependencies](#2-install-dependencies)
3. [Environment file](#3-environment-file)
4. [Application key](#4-application-key)
5. [Database](#5-database)
6. [Migrations](#6-migrations)
7. [Passport (API authentication)](#7-passport-api-authentication)
8. [Run the app](#8-run-the-app)
9. [Tests and code style](#9-tests-and-code-style)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.5 | `composer.json` requires `^8.5` |
| Composer | 2.x | |
| Node.js and npm | current LTS | Vite and the frontend build |
| PostgreSQL | 16 or newer | The real database. Tests use SQLite instead |
| Laravel Herd | latest | Serves the app at `https://agent-1o1-ai.test` |

## 2. Install dependencies

```bash
composer install
npm install --ignore-scripts
npm run build
```

`composer run setup` runs the install, env copy, key generation, migrations, setup data sync and build in one go. It runs `migrate --force` against whatever database `.env` points at, so do sections 3 to 5 first if you want it to use PostgreSQL.

## 3. Environment file

Copy the template and edit the copy. Never commit `.env`.

```bash
cp .env.example .env
```

`.env.example` is grouped into labeled sections (Application, Database, Mail, AI Providers, Authentication, Billing, Triggers and so on). Keep that layout when you add a variable: put it in its section and, if it is optional, comment it out with a one-line note.

Back up an existing `.env` before replacing it:

```bash
cp -p .env .env.backup && chmod 600 .env.backup
```

Git ignores `.env.backup`. It holds secrets, so keep it out of anything shared.

## 4. Application key

```bash
php artisan key:generate
```

This writes `APP_KEY` into `.env`. Run it once per environment. Generating a new key later invalidates encrypted data and sessions made with the old one.

## 5. Database

The template defaults to SQLite (`DB_CONNECTION=sqlite`). Development and production use PostgreSQL, so switch to it.

### 5.1 Create the database

```bash
psql -h 127.0.0.1 -U <your-user> -d postgres -c "create database agent1o1_dev"
```

Postgres folds unquoted names to lowercase, so use lowercase names. To list databases:

```bash
psql -h 127.0.0.1 -U <your-user> -d postgres -Atc "select datname from pg_database where not datistemplate order by 1"
```

### 5.2 Point `.env` at it

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agent1o1_dev
DB_USERNAME=<your-user>
DB_PASSWORD=
```

Leave `DB_PASSWORD` empty if your local Postgres lets your user connect without one.

Use port `5432` for Postgres. The template's commented `DB_PORT=3306` is the MySQL default, and a MySQL server may be running on that port on the same machine.

### 5.3 Check which database Laravel is using

```bash
php artisan config:show database.default
php artisan config:show database.connections.pgsql.database
php artisan db:show
```

`db:show` lists the tables of the connected database. A new database shows `Tables .. 0`.

## 6. Migrations

Create the schema on an empty database:

```bash
php artisan migrate
```

To drop every table and rebuild from scratch:

```bash
php artisan migrate:fresh
```

`migrate:fresh` deletes all data in the database named in `.env`. Check the name with `config:show` first.

Things to know:

- **Create migrations hold the whole table.** Columns, indexes and defaults live in the table's own `create_*_table` migration. Add a new column by editing that migration only while no shared database has run it. Otherwise add a new alter migration.
- **Order matters.** A foreign key needs its target table to exist, so a table's create migration must sort after the one it points to. Rename the timestamp if you need to reorder.
- **Four foreign keys are added at the end.** `add_deferred_foreign_keys` adds them because the tables point at each other or at themselves: `users.current_workspace_id`, `workflows.current_version_id`, `runs.parent_node_id` and `agent_sessions.parent_session_id`.
- **Existing databases need a rebuild.** Several create migrations were renamed. A database that already ran the old file names sees the renamed ones as new, so run `migrate:fresh` on it.

### 6.1 Setup data and first admin

A migrated database is empty, and the editor shows no nodes, triggers or connectors until the setup data is loaded. It holds plans, node and app categories, trigger presets, connectors, the model catalog and prices, and the agent and page templates. There are no fake users or workspaces.

```bash
php artisan setup:catalog:sync
php artisan setup:catalog:status
php artisan super-admin:create you@example.com --name="Your Name" --role=owner
```

- `setup:catalog:sync` is safe to repeat. A dataset whose code has not changed is skipped without any writes. Add `--only=connectors` for one dataset (its dependencies come along), `--force` to re-apply, or `--dry-run` to see what would run.
- `setup:catalog:status` shows each dataset's owner, row count and state. It exits non-zero if anything is out of date, empty or failed.
- The data lives in `database/setup/*.php` (plain arrays) and the rules for writing it in `app/Services/Setup/Datasets/`. Change a file and the next sync re-applies just that dataset.
- Before writing anything, each dataset checks its own definitions (duplicate keys, a connector with no app category, a model with no routes). A problem stops that dataset, and anything depending on it, with a message saying what is wrong. `setup:catalog:sync --dry-run` runs the same checks without writing.
- Nothing runs this for you on deploy or at container start. When you are ready, run `php artisan migrate --force` and then `php artisan setup:catalog:sync` yourself, and check the result with `setup:catalog:status`. Use `setup:catalog:sync --dry-run` first to see what would change without writing.
- The super-admin health check `setup_data` (in `GET /system/health`) is `down` while a dataset is missing or failed, and `degraded` when the code changed since the last sync.
- Plans, the referral program and model prices are created once and then belong to the admin. A sync never overwrites their edits.

## 7. Passport (API authentication)

The `api` guard uses Passport. The migrations create the `oauth_*` tables. Two things remain: the encryption keys and a password client.

### 7.1 Check the current state

```bash
ls -l storage/oauth-*.key
psql -h 127.0.0.1 -U <your-user> -d <database> -Atc "select count(*) from oauth_clients"
```

You want both key files and `0` clients on a fresh database.

### 7.2 Create the keys (only if missing)

```bash
php artisan passport:keys
```

Add `--force` only to replace existing keys. Replacing them signs everyone out.

### 7.3 Create the password client

```bash
php artisan passport:client --password
```

If the flags differ in your Passport version, check `php artisan passport:client --help`. The command prints a client ID and a secret. The secret is shown only once, so copy it straight away.

### 7.4 Add the client to `.env`

```dotenv
PASSPORT_PASSWORD_CLIENT_ID=<the id>
PASSPORT_PASSWORD_CLIENT_SECRET=<the secret>
```

Then clear any cached config:

```bash
php artisan config:clear
```

### 7.5 Check that it works

Sign in through the login endpoint and confirm the response contains an access token.

`PASSPORT_PASSWORD_CLIENT_ID` is blank in `.env.example`, and a blank value is fine for `migrate` and `migrate:fresh`. The migration that adds the `social_exchange` grant skips blank values.

## 8. Run the app

Herd serves the site, so there is no need to run `php artisan serve` yourself. Use `composer run dev` to start the supporting processes:

```bash
composer run dev
```

It starts the server, the Horizon queue listener, Reverb (websockets), the log viewer (Pail) and Vite.

If a frontend change does not show up, run `npm run build` or keep `npm run dev` running.

## 9. Tests and code style

```bash
php artisan test --compact
php artisan test --compact --filter=SomeTest
vendor/bin/pint --dirty --format agent
```

The test suite runs on SQLite, so it does not touch your PostgreSQL database. Run Pint before you commit any PHP change.

## 10. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Your database tool shows many tables, but `db:show` says 0 | The tool is connected to a different database, or it is listing system tables (`pg_catalog`, `information_schema`). Check the database name at the top of the tool's panel and reconnect. |
| The wrong database is in use after editing `.env` | Cached config. Run `php artisan config:clear`. |
| `migrate:fresh` fails with an `oauth_clients` or uuid error | Fixed in the `social_exchange` migration, which now skips a blank `PASSPORT_PASSWORD_CLIENT_ID`. Pull the latest code. |
| `relation "..." does not exist` while migrating | A create migration sorts before the table it points at. See the order notes in [Migrations](#6-migrations). |
| The editor shows no nodes, categories or trigger presets | The setup data was never loaded. Run `php artisan setup:catalog:sync`, then `setup:catalog:status`. In the admin app, the `setup_data` health check shows the same thing. |
| Connects to MySQL instead of Postgres | `DB_PORT` is `3306`. Set `DB_CONNECTION=pgsql` and `DB_PORT=5432`. |
| Search over embeddings is slow | `document_embeddings.embedding` is a `json` column, and the `pgvector` extension is not installed, so there is no vector index. |

## Adding to this guide

Add new steps under the section they belong to, and a row to Troubleshooting for any error that cost time. Keep commands copy-pasteable and say what each one changes.
