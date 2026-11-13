# Backup and Restore Runbook

How the production Postgres database (hosted on Coolify) is backed up, and how to restore it. Menu names in Coolify change between versions; the steps describe the intent, so adjust the clicks if the UI differs.

**Targets to agree before launch** (write the real numbers here once you have measured them):

- Backup interval (= worst-case data loss): _____ hours
- Measured restore time from the last restore test: _____ minutes
- Date of the last successful restore test: _____

---

## 1. What is backed up, and where

| Data | Backed up by | Lives |
| --- | --- | --- |
| Postgres (users, workspaces, workflows, runs, billing, embeddings) | Coolify scheduled backup | S3-compatible bucket, **off the Coolify server** |
| `APP_KEY` | Copied by hand | Password manager (not on the server) |
| Passport keys (`PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`) | Copied by hand | Password manager |
| All other production env vars (Stripe, mail, AI providers) | Copied by hand | Password manager |
| Uploaded files, exports, artifacts | Bucket versioning | File-storage bucket (`FILESYSTEM_DISK`) |
| Redis (queues, cache) | Not backed up | Rebuildable. In-flight jobs are lost; stuck steps are recovered by `FailStuckNodeRunsJob` |

**`APP_KEY` matters as much as the database.** Connector tokens, BYOK AI keys, 2FA secrets and workspace secrets are encrypted with it. A restored database with a different `APP_KEY` is unreadable for all of those. Never restore into an app that has a different key.

---

## 2. One-time setup

### 2.1 Off-server storage
1. Create a bucket on S3, Cloudflare R2 or Backblaze B2, ideally in a different account from the server host.
2. In Coolify open **Storages** (S3), add endpoint, bucket, access key and secret, then **Validate**.

### 2.2 Scheduled database backup
1. Open the Postgres resource, then the **Backups** tab, then add a scheduled backup.
2. Frequency: a cron such as `0 3 * * *` (daily), or `0 */6 * * *` (every 6 hours) if a day of loss is too much.
3. Turn on **Save to S3** and select the storage from 2.1.
4. Retention: for example 14 backups kept locally and 30 or more in S3.
5. Run **Backup Now** and confirm the file appears in the bucket.

### 2.3 Postgres image
The database must support `pgvector` (CI uses `pgvector/pgvector:pg17`). On the Postgres resource confirm the image is a pgvector one. After any restore, `CREATE EXTENSION IF NOT EXISTS vector;` must succeed.

### 2.4 Secrets
Store `APP_KEY`, the Passport keys and the rest of the production env in the password manager and update them whenever they change. Do not rely on Coolify as the only copy.

### 2.5 File storage
Turn on versioning on the uploads bucket so overwritten or deleted files can be recovered.

---

## 3. Restore test (before launch, then monthly)

Never practise on production. Use a scratch database.

1. In Coolify create a second, empty Postgres resource using the same pgvector image. Note its connection URL.
2. Download the most recent backup from the bucket.
3. Restore it:
   ```bash
   pg_restore --no-owner --no-privileges --clean --if-exists \
     -d "postgresql://USER:PASSWORD@HOST:PORT/DB" backup.dmp
   ```
   If the backup is plain SQL rather than a custom-format dump, use `psql -d "<url>" -f backup.sql` instead.
4. Check the extension and row counts:
   ```sql
   SELECT extname FROM pg_extension WHERE extname = 'vector';
   SELECT count(*) FROM users;
   SELECT count(*) FROM workspaces;
   SELECT max(created_at) FROM runs;
   ```
   The newest `runs` row should be close to the time the backup was taken.
5. Start a staging copy of the app against the scratch database, **with production's `APP_KEY`**, and:
   - log in with a real test account;
   - open a connector credential or AI provider key and confirm it decrypts (no error, hint shown);
   - run one simple workflow end to end.
6. Record the elapsed time at the top of this file, then delete the scratch database.

A backup that has never been restored is not a backup.

---

## 4. Real restore (production incident)

Use when production data is lost or corrupted. Decide first whether you really need a restore: a bad deploy is a rollback, not a restore.

1. **Stop the writers.** In Coolify stop Horizon, the scheduler and Reverb, then put the web service in maintenance (`php artisan down`) or stop it. This prevents new writes and queue work from touching a half-restored database.
2. **Keep the damaged database.** Do not drop it yet. Rename it or restore into a new Postgres resource, so you can compare or salvage rows.
3. **Pick the backup.** The latest one before the incident began, not necessarily the latest overall. Note its timestamp: everything after it is lost.
4. **Restore** into the new resource as in section 3 steps 1 to 4.
5. **Point the app at it.** Update the DB host, name and credentials in the production env (Coolify), keeping `APP_KEY` and the Passport keys unchanged.
6. **Start the web service only** and check:
   - `/up` is healthy and a login works;
   - encrypted values decrypt (section 3 step 5);
   - `php artisan migrate:status` shows nothing pending. If a deploy happened after the backup, run `php artisan migrate --force`.
7. **Start Horizon, the scheduler and Reverb.** Take the app out of maintenance.
8. **Reconcile** (section 5), then tell affected users what period of data was lost.

---

## 5. Reconcile after a restore

The restored database is older than the outside world. Fix the differences:

- **Stripe.** Stripe holds the newer truth for payments made after the backup.
  - In the Stripe dashboard, list events since the backup time. Resend any event the database does not know about from the webhook endpoint's event log: the webhook handler is idempotent (`ProcessedWebhookEvent`), so replays are safe.
  - Review the `StripeWebhookFailure` table for unresolved events.
  - Compare active subscriptions in Stripe with the plans in the database for the affected window.
- **Credits.** Credit balances in the restored database may not include usage or purchases made after the backup. Check pack purchases against Stripe and grant anything missing.
- **Runs.** Runs that were `running` at backup time have no worker any more. `FailStuckNodeRunsJob` and `FailStuckAgentTurnsJob` fail or retry them within minutes; check **Pulse** / the stuck-work report afterwards.
- **Webhook triggers and provider watches.** Provider subscriptions (for example Gmail or YouTube watches) may have expired or been renewed since. `triggers:renew-watches` runs every ten minutes; confirm it ran.
- **Files.** Database rows may reference files uploaded after the backup, or miss ones deleted. Use bucket versioning to recover deleted files.
- **Tell users.** State the window of lost data and ask them to re-do anything made in it.

---

## 6. Calendar

| When | Do |
| --- | --- |
| Before launch | Manual backup, then a full restore test (section 3) |
| Daily | Glance at the backup job: it ran and the file is in the bucket |
| Monthly | Restore test, then update the numbers at the top |
| After changing the env or keys | Update the password-manager copy |
| After a Postgres image or major version change | Restore test, including the `vector` extension |

---

## 7. Limits to remember

- Coolify backups are scheduled `pg_dump` snapshots, not point-in-time recovery. Data written since the last backup is lost on a restore. If that is not acceptable, add continuous WAL archiving (pgBackRest or WAL-G).
- Coolify does not test restores for you.
- If the Coolify server itself is lost, the bucket and the password-manager copies are all you have. Keep both current.
