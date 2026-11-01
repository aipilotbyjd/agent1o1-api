# App Triggers Plan (v2)

Status: proposed. Owner: triggers. Supersedes the "app trigger" parts of `TRIGGERS_PLAN.md`; the core pipeline in that document (receive, fire, breaker, caps) stays as it is.

Provider facts marked **(verify)** are from memory of public docs and must be re-checked against the current provider documentation before the matching phase starts. Numbers in the capacity model are assumptions to replace with real data.

---

## 0. Summary

Today every app trigger is a reader node run on a timer. That is correct for apps with no push, but it is the wrong default for Gmail, Slack, Outlook, Drive and Calendar, where it gives minutes of delay, burns provider quota on every poll, and (for Slack) is becoming rate-limited **(verify)**.

The plan:

1. Introduce one **Trigger Source** abstraction with six delivery mechanisms behind it, so a trigger's author never cares how events arrive.
2. Put a **durable inbox** between the outside world and the existing trigger pipeline, so ingestion is fast, replayable and rate-isolated.
3. Run a **control plane** (a desired-state reconciler) that owns every provider subscription: create, share, renew, repair, remove.
4. Make everything **shareable and indexed**: one provider subscription or one poll serves many triggers, and nothing scans all triggers per minute.
5. Ship it behind flags, in shadow mode first, provider by provider, with measurable exit criteria.

---

## Implementation status

Built (code written; tests written but run by the team, not yet verified in CI):

- **Foundation:** indexed poll claim (`triggers.next_poll_at`), `provider_events` inbox, `trigger_subscriptions`, shared `provider_watches`, `PollReader` shared by polls and samples, computed trigger status, sample and replay API.
- **Instant delivery:** Slack (messages, direct messages and reactions added or removed carry the item in the event; new channels, people, files, joins, mentions and pins wake the trigger's own poll), Gmail (the watch listens to the labels the mailbox's triggers use), Outlook (mail folders and the calendar), Google Drive, Calendar, Sheets, Docs (through Drive file channels), Forms (Pub/Sub), YouTube uploads (WebSub), GitHub (repository hooks for issues, pull requests, pushes, releases, stars, forks, branches and tags, comments, workflow runs), Notion (integration webhooks), Linear (a webhook per team and record type) and Jira (a dynamic webhook per site, event and project). Where the provider only says "something changed", the trigger's own poll fetches it, so filters, order and dedupe are the poll's.
- **Polling-only triggers** (the provider has no push): Google Tasks and Contacts, YouTube comments, GitHub new repositories, Notion new workspace members. They back off when quiet (`triggers.adaptive_poll`). "Slack reaction removed" is the reverse: Slack never lists removals, so it works only through instant delivery and says so when that is off.
- **Scheduled polls:** a node can set its own next poll time from what it read (`SchedulesTriggerPolls`): "event starting soon" in Google Calendar and Outlook polls when the next event enters its lead time, not every minute.
- **Control plane:** `WatchManager` (share, renew, grace-period release), `triggers:renew-watches` every 10 min, `triggers:collect-watches` hourly, `triggers:subscriptions` to inspect or nudge, missed-push detection (a reconciliation poll that finds something marks the subscription degraded; the next push event recovers it).
- **Delivery controls:** start from now or the beginning, runs per minute, group events (digest), per-trigger and per-workspace concurrency caps, plan-based polling floor (`min_poll_interval_minutes` in a plan's limits).
- **Frontend:** status chip, health hints with one action, fetch sample for every trigger type, "Listen for event", replay, event history with held and grouped events.

- **"Deleted" triggers** (the provider has no feed of removals, so the trigger keeps state between polls: `KeepsTriggerState`, `PresenceTracker`):
  - *Google Sheets: Row deleted* (`google_sheets_list_deleted_rows`): keeps the values of a chosen id column and reports the ones that left the sheet. Row numbers can't be used because they shift when a row above goes, so the sheet needs an id column; an edited id counts as deleted.
  - *Notion: Database item deleted* (`notion_list_deleted_database_items`): reads the whole database each poll (Notion's queries leave out trashed items) and reports ids that disappeared. An item moved to another database, or one that stops matching "Only items matching", also counts as gone. Instant wake-up on `page.deleted`, `page.moved` and `page.properties_updated`.
  - *Google Drive: File trashed* (`google_drive_list_trashed_files`): reads Drive's change feed from a saved page token (Drive doesn't say when a file was trashed outside shared drives) and reports files trashed or permanently deleted. A permanently deleted file has no details left, so a folder or type filter can't be checked against it and it is skipped when one is set. Shares the account's Drive push channel.
  - How state is kept: `triggers.poll_cursor.state`, saved only after a poll succeeds (a failed read repeats from the same state). The first poll is a baseline and reports nothing. Changing the node's settings starts a new baseline (`_config` fingerprint), so an edited id column doesn't report every row gone. Removals are delivered under `removed:{sha1(id@last_seen_at)}`, so a repeated poll can't report one twice. At most `triggers.poll_max_items` per poll, the rest carried in the state. A list longer than `triggers.removal.max_tracked` (2000) is refused with a message rather than watched halfway.
- **Google Tasks: Task due soon** (`google_tasks_list_due_soon_tasks`): fires once per task per due date, `hours_before` the due day starts until that day ends. Tasks keeps only the day, so the lead counts from 00:00 UTC of the due day. The poll wakes itself when the next task comes within its lead (`SchedulesTriggerPolls`), like Calendar's "event starting soon".
- **Linear and Jira** (polling every minute, like Gumloop's): *Linear: New issue*, *Linear: Issue updated*, *Linear: New comment*; *Jira: New issue*, *Jira: Issue updated*. New items are those created inside a six-hour look-back window, told apart by the usual delivery id (`id`, or `id:updated_at` for updates), so nothing runs twice and a burst between polls is not missed. An update that arrives within 30 seconds of the creation is treated as the creation, so a new issue doesn't also fire "updated". Filters: Linear by team (required for the trigger), status, project, priority, labels and assignee; Jira by fields, a JQL query or a saved filter (one at a time). Setup: `LINEAR_CLIENT_ID`/`LINEAR_CLIENT_SECRET` and `JIRA_CLIENT_ID`/`JIRA_CLIENT_SECRET` (an Atlassian OAuth 2.0 (3LO) app whose callback is the connector callback); no other setup.
- **Telegram, Discord, Twilio** (see "Messaging" in `docs/NODES_CATALOG.md`): connected with the member's own keys, so on by default (`TRIGGERS_PUSH_TELEGRAM_ENABLED`, `_DISCORD_`, `_TWILIO_`; switch off to stop registering webhooks). *Telegram* triggers (new message, edit, channel post, button pressed, member joined or left, poll answered, bot added or removed, join request) are push-only: one webhook per bot (`TelegramSource`, shared by the bot's triggers, with a secret) and no polling fallback, because Telegram never lists updates. *Discord* slash command and button triggers are instant over the application's Interactions URL (`DiscordSource`, Ed25519); new message (every minute), thread, channel and member triggers poll, as Discord pushes those only over a gateway. *Twilio* "message received" is instant for a text to a named number (`TwilioSource`, the number's incoming-message URL, signature checked with the auth token) and polls otherwise, and for WhatsApp; failed messages, received calls and recordings poll. Each source refuses a bot, application or number that already points at another webhook and says where.
- **Typeform, Mailchimp, Kit, ActiveCampaign** (see "Marketing" in `docs/NODES_CATALOG.md`): connected with the member's own keys, so on by default (`TRIGGERS_PUSH_TYPEFORM_ENABLED`, `_MAILCHIMP_`, `_KIT_`, `_ACTIVECAMPAIGN_`). Each registers a webhook on the provider (`TypeformSource`, `MailchimpSource`, `KitSource`, `ActiveCampaignSource`, built on `AbstractTokenWatchedSource`) whose delivery only pokes the trigger's poll, so the poll's filters, order and dedupe decide what runs. Typeform checks the `Typeform-Signature` HMAC with a per-webhook secret; the other three carry a random token in the callback URL (`AbstractTokenIngress`, matched by its hash). **Resend, Postmark, Mailgun** do the same (`TRIGGERS_PUSH_RESEND_ENABLED`, `_POSTMARK_`, `_MAILGUN_`): Resend registers one webhook per set of email events; Postmark one per event and message stream, and for received email the server's single inbound URL, only when it is empty (otherwise the trigger is refused with a sentence naming where it points, and keeps polling); Mailgun adds the token URL to the domain's webhook for each event beside any the account already has (up to three) and removes only its own. **Klaviyo, Brevo and SendGrid** poll only: none offers a webhook that can be registered through the API per trigger.
- **Trello, Asana, ClickUp, monday.com, Airtable** (see "Project and docs tools" in `docs/NODES_CATALOG.md`): connected with the member's own token, on by default (`TRIGGERS_PUSH_TRELLO_ENABLED`, `_ASANA_`, `_CLICKUP_`, `_MONDAY_`, `_AIRTABLE_`). Same "poke the poll" design: a webhook on the board, project, list or base (`TrelloSource`, `AsanaSource`, `ClickUpSource`, `MondaySource`, `AirtableSource`) whose delivery only makes the trigger's poll look now. Trello, Asana and monday.com check the new URL at once (a HEAD request, an `X-Hook-Secret` echo, a `challenge` echo), which their ingresses answer; Airtable's webhook is refreshed every three days. Triggers whose board, project, list or base is mapped from an earlier step keep polling.
- **Pipedrive, Zoho CRM** (see "CRM" in `docs/NODES_CATALOG.md`): on by default (`TRIGGERS_PUSH_PIPEDRIVE_ENABLED`, `_ZOHO_`). Pipedrive registers a webhook per object and action (`PipedriveSource`); Zoho opens a notification channel per module and change (`ZohoSource`), renewed every twelve hours. Both only make the trigger's poll look now. **HubSpot and Salesforce** poll only: HubSpot's webhooks need a developer app and Salesforce's change events need a streaming connection.
- **Zoho Desk** (see "Zoho business apps" in `docs/NODES_CATALOG.md`): on by default (`TRIGGERS_PUSH_ZOHO_DESK_ENABLED`). `ZohoDeskSource` creates a webhook per event (`Ticket_Add`, `Ticket_Update`, `Ticket_Thread_Add`) with a token URL, renews it daily (Desk switches a webhook off after repeated failures) and makes it again if it is gone; a delivery only makes the trigger's own poll look now. Zoho Books, Projects, Campaigns, Mail and Cliq have no webhook registration in their APIs, so their triggers poll; a webhook set up in the app can feed a generic Webhook trigger.
- **Waiting for quiet (debounce)**: `debounce_seconds` on any trigger holds events until none has arrived for that long, then runs once with all of them (`items`, `count`, and the newest in `latest`). `triggers.debounce.max_wait_seconds` (3600) forces a run for a source that never goes quiet. Shares the held-event path with "group events" (not allowed together; publish refuses both). Checked by `triggers:flush-batches` every minute, so a wait is accurate to about a minute.
- **Storm detector**: a trigger that takes in more than `storm_limit` events (default `triggers.storm.max_events_per_minute`, 300) in one minute switches itself off, the event that crossed the line is recorded as skipped with the reason, and owners are notified. `triggers.disabled_reason` records why, so the status chip reads "Off: too many events". Triggers that group or wait for quiet are exempt (collapsing bursts is their job), and "Run now" and filtered or duplicate events aren't counted. A fixed one-minute counter in the cache, so it is cheap and shared across workers.

Not built:

- Teams, Shopify, HubSpot, Salesforce, Zendesk (no connector or reader node yet). Stripe and Typeform are built.
- Event quotas (a per-plan cap on events per month), the admin console, canaries, provider simulators and load tests.

**Verify against the providers before relying on these** (written from the public docs, not exercised against live accounts): Telegram's `setWebhook` (`secret_token`, `allowed_updates`), Discord's `PATCH /applications/@me` for `interactions_endpoint_url` and its PING check, Twilio's `IncomingPhoneNumbers` update of `SmsUrl`, Linear's `webhookCreate` (the `admin` requirement, `allPublicTeams`, the delivery fields `webhookId` and `webhookTimestamp`) and Jira's `/webhook` and `/webhook/refresh` calls (the scope, the `jqlFilter` limits, the `X-Atlassian-Webhook-Identifier` header), the Drive `changes.list` fields and the wording of its expired-token error (the reset matches "page token"), Notion's `page.deleted` event name and its `parent` data, and Tasks returning only the due day.

Setup outside the app, per provider (each is off until its `TRIGGERS_PUSH_*_ENABLED` switch is on, and needs `APP_URL` to be a public HTTPS address):

| Provider | What to set up |
|---|---|
| Slack | In the connector's Slack app: Events API request URL `/api/hooks/apps/slack`; bot events `message.channels`, `message.groups`, `message.im`, `reaction_added`, `reaction_removed`, `pin_added`, `app_mention`, `member_joined_channel`, `channel_created`, `team_join`, `file_shared`; the scopes in the connector (`channels:history`, `groups:history`, `im:history`, `im:read`, `reactions:read`, `pins:read`, ...). Set `TRIGGERS_SLACK_SIGNING_SECRET`. Existing accounts must reconnect for new scopes. |
| Gmail | A Pub/Sub topic that `gmail-api-push@system.gserviceaccount.com` may publish to, and a push subscription to `/api/hooks/apps/gmail?token=...`. Set `TRIGGERS_GMAIL_PUBSUB_TOPIC` and `TRIGGERS_GMAIL_PUSH_TOKEN`. Gmail's restricted scope needs Google's OAuth verification before real customers can use it. |
| Outlook | Nothing beyond the connector: the app creates Graph subscriptions with the account (`Mail.ReadWrite`, `Calendars.ReadWrite`). |
| Drive, Calendar, Sheets, Docs | Nothing beyond the connector and its switch (`TRIGGERS_PUSH_GOOGLE_*_ENABLED`). |
| Forms | A Pub/Sub topic that `forms-notifications@system.gserviceaccount.com` may publish to, and a push subscription to `/api/hooks/apps/google_forms?token=...`. Set `TRIGGERS_GOOGLE_FORMS_PUBSUB_TOPIC` and `TRIGGERS_GOOGLE_FORMS_PUSH_TOKEN`. |
| YouTube | Nothing: the app subscribes through Google's WebSub hub. The callback URL must be public HTTPS. |
| GitHub | The connected account needs admin rights on the repository; otherwise the trigger keeps polling and shows why. |
| Notion | In the integration's Webhooks tab set the URL to `/api/hooks/apps/notion`. Notion posts a verification token, which is written to the log once; set `TRIGGERS_NOTION_VERIFICATION_TOKEN` to it and verify in Notion. Accounts connected before this existed must reconnect (the workspace id is kept at connect time). |
| Linear | Set `TRIGGERS_PUSH_LINEAR_ENABLED=true`, then **reconnect** Linear accounts: creating a webhook through the API needs the `admin` scope, which the connector asks for only while the switch is on (admin is broad; accounts without it keep polling and the trigger says why). The app creates one webhook per team (or all public teams) and record type (`Issue`, `Comment`) with its own secret, checks `Linear-Signature` (HMAC-SHA256, hex) and a delivery no older than five minutes, and turns a webhook back on if Linear disabled it after failed deliveries (daily check). Needs `APP_URL` public HTTPS. |
| Jira | Set `TRIGGERS_PUSH_JIRA_ENABLED=true`, then **reconnect** Jira accounts for the `manage:jira-webhook` scope. The app registers a dynamic webhook per site, event (`jira:issue_created` or `jira:issue_updated`) and project (the project only when the node chooses issues by fields), extends each every 20 days (Jira expires them at 30) and registers it again, with a new token, if Jira has dropped it. Jira doesn't sign these deliveries, so each webhook's URL carries a random token, kept hashed; a delivery only makes the poll look now. Needs `APP_URL` public HTTPS. |
| Telegram | Nothing: connect a bot token (from @BotFather). The app calls `setWebhook` itself with a secret and the `?bot=<id>` URL; `APP_URL` must be public HTTPS (Telegram needs port 443, 80, 88 or 8443). A bot that already has another webhook is refused. In groups, turn off privacy mode (`/setprivacy` in @BotFather) to see every message; the trigger warns when it is on. |
| Discord | For slash commands and buttons: put the **Application ID** and **Public key** on the connection (Developer Portal > General Information). The app sets the application's Interactions URL (`/api/hooks/apps/discord?app=<id>`), which Discord verifies with a signed PING, so `APP_URL` must be public HTTPS and `ext-sodium` available. New member needs the *Server Members* intent and message text the *Message Content* intent, both toggled in the Developer Portal. |
| Twilio | Nothing beyond the connection (Account SID and Auth Token). For an instant text trigger, choose the number: the app sets its incoming-message URL to `/api/hooks/apps/twilio` and restores the old one when the trigger goes; a number with a TwiML app or another URL stays on polling. WhatsApp senders are polled (their webhook is a Console setting). |
| Typeform | Nothing beyond the connection (personal token; tick the EU data center if the account is on it). The app creates a webhook on the form and removes it when the trigger goes. `APP_URL` must be public HTTPS. |
| Mailchimp | Nothing beyond the connection (API key). The app adds a webhook to the audience for the chosen events. |
| Kit | Nothing beyond the connection (API key). The app adds subscriber and purchase webhooks. |
| ActiveCampaign | Nothing beyond the connection (account URL and API key). The app adds a webhook for the chosen events. |
| Klaviyo, Brevo, SendGrid | Nothing: these triggers poll every few minutes. |
| Trello | Nothing beyond the connection (API key and token from trello.com/power-ups/admin). The app creates a webhook on the board and removes it when the trigger goes. |
| Asana | Nothing beyond the connection (personal access token). The app creates a webhook on the project. |
| ClickUp | Nothing beyond the connection (personal API token). The app creates a webhook on the list; the trigger needs the workspace and list picked. |
| monday.com | Nothing beyond the connection (API token). The app creates the board's webhooks (one per event). |
| Airtable | The token needs the `webhook:manage` scope for the base. The app creates the webhook and refreshes it every three days. |
| HubSpot, Salesforce | Nothing: these triggers poll every few minutes. |
| Pipedrive | Nothing beyond the connection (API token). The app creates a webhook per object and action; the Pipedrive user must be allowed to manage webhooks, otherwise the trigger keeps polling. |
| Zoho CRM | Nothing beyond the connection (client id, secret, refresh token, data center). The refresh token needs the `ZohoCRM.notifications.ALL` scope as well for instant triggers; without it they keep polling. |
| Zoho Desk | Nothing beyond the connection (client id, secret, refresh token with the `Desk.settings.ALL` scope as well, data center, organization id). Creating a webhook needs a Desk admin; without it the trigger keeps polling. |
| Zoho Books, Projects, Campaigns, Mail, Cliq | Nothing: these triggers poll every few minutes. |
| Resend | Nothing beyond the connection (API key; a sending-only key can send but cannot create the webhook, so use a full-access key for triggers). |
| Postmark | Nothing beyond the connection (server token). For "Email received", the server's inbound webhook must be empty; the app sets and clears it. |
| Mailgun | Nothing beyond the connection (private key, sending domain, region). The key needs access to the domain's webhooks. |

---

## 1. Goals, non-goals, service levels

### Goals
- Instant triggers (seconds) wherever the provider offers push or a change feed; adaptive polling elsewhere.
- No silent stops: every failure becomes a visible state with a fix.
- No lost events across restarts, outages, expired subscriptions and provider hiccups (at-least-once in, effectively-once out).
- Linear cost growth: provider calls scale with the number of **distinct resources watched**, not the number of triggers.
- A new provider is mostly a new adapter class plus fixtures, not new infrastructure.

### Non-goals (for now)
- Arbitrary customer-defined webhooks per app (the generic Webhook trigger already covers it).
- Cross-region active-active ingestion.
- A visual builder for custom polling code (the "Create with AI" style trigger is a later, guard-railed phase).

### Service levels (targets, measured per provider)

| Indicator | Target |
|---|---|
| Push: provider event time to run start, p95 | under 5 s |
| Push: provider event time to run start, p99 | under 30 s |
| Polling lag, p95 | under 1.5x the trigger's interval |
| Events lost (verified by canary and reconciliation) | 0 |
| Subscription renewal success | 99.9 % without human action |
| Ingest endpoint response time, p99 | under 300 ms |
| Time to detect a broken subscription | under 15 min |

---

## 2. What exists today (and what it costs)

| Area | Today | Problem at scale |
|---|---|---|
| Delivery | `PollTrigger` per trigger, driven by `triggers:run-due` | Every minute `queueDuePolls()` loads **all** active polling triggers. O(n) per minute. |
| Sharing | None. Two triggers on one inbox poll it twice. | Quota use and cost grow with trigger count. |
| Push | None for workflows. A signed Slack Events endpoint exists for the assistant only. | Delay, quota burn, Slack read limits. |
| Subscription lifecycle | Not applicable (polling) | Needed for push: renew, repair, clean up. |
| Windows | Fixed page per poll (widened in the last change) | A burst larger than the page loses older items. |
| Test and sample data | None | Users can't map fields before the first live event. |
| Status | On / paused / off after failures | No "reconnect", "renewing", "degraded". |
| Catalog | 8 readers | Linear, Jira, HubSpot, Notion, Airtable, Typeform, Stripe, Shopify, Teams, Monday missing. |
| Modes | "New item" only | No "updated" or "deleted"; no "start from". |

Reusable assets: the core pipeline (`TriggerService::receive`, `FireTriggerEvent`, circuit breaker, concurrency caps, replay, catch-up), `ConnectorCredential` and `ConnectorTokens`, `SupportsTriggerMode` nodes (become the polling adapters), `AssistantSlackEventsController` (signature and challenge code), Reverb (live test events), Pulse and the super-admin console (observability).

---

## 3. Delivery mechanisms (the taxonomy)

Every source declares which of these it supports and in what order of preference.

| Code | Mechanism | Examples | Notes |
|---|---|---|---|
| **P1** | Platform-app push: one endpoint and one registration per provider app, routing by key inside the payload | Slack Events API, HubSpot app webhooks, GitHub App, Gmail via Pub/Sub topic | Cheapest at scale. One subscription can serve many triggers. |
| **P2** | Per-resource webhook: registered on the customer's resource through the provider API | GitHub repo hooks, Linear, Typeform, Shopify, Stripe, Monday, Jira | Needs create and delete lifecycle, secrets per subscription, expiry handling where applicable. |
| **P3** | Notification plus change feed: push says "something changed", a cursor fetches what | Drive `changes`, Calendar sync token, Graph delta, Gmail `history`, Airtable payload cursor | Push gives speed, cursor gives correctness. Best loss-resistance. |
| **P4** | Adaptive polling | Notion, Sheets rows, Salesforce, anything without push | Interval adapts to activity; shared by resource; conditional requests. |
| **P5** | Time-derived | "N minutes before a calendar event", "no reply after 30 min" | Built on stored synced state plus the scheduler, not on provider events. |
| **P6** | Custom polling (AI-assisted) | User-described monitors | Sandboxed, quota-limited, later phase. |

**Rule:** P1/P2/P3 push is the primary path. Polling (P4) is both a first-class mechanism for apps without push **and** the automatic fallback for any push source that goes quiet or degraded. A push source with a change feed also runs a slow **reconciliation poll** (for example hourly) to prove nothing was missed.

---

## 4. Target architecture

```
                         CONTROL PLANE (desired-state)
 publish/edit ─▶ DesiredSubscriptions ─▶ Reconciler ─▶ Provider APIs
 pause/delete ─┘        │                   │   ▲ renew / repair / GC
                        ▼                   ▼   │
                 Subscription registry ◀─ Renewal scheduler (due-index)
                        │
 DATA PLANE             │ routing index
 ────────────────────── ▼ ──────────────────────────────────────────────
 provider push ─▶ INGRESS (verify, ack ≤300ms) ─▶ INBOX (durable, partitioned)
 poll scheduler ─▶ POLLER (shared fetch) ─────────┘          │
 change-feed cursor fetch ────────────────────────────────────┤
                                                               ▼
                                      ROUTER (resource key → subscriptions → consumers)
                                                               ▼
                               ADAPTER pipeline: normalise → filter → expand (once, cached)
                                                               ▼
                            TriggerService::receive()   (existing, unchanged)
                                                               ▼
                                   FireTriggerEvent ─▶ Run
```

### 4.1 Components

| Component | Responsibility | Scaling note |
|---|---|---|
| **Ingress** | Verify signature or token, answer handshakes, write one inbox row, return. No provider calls, no run starting. | Stateless. Scale horizontally. Can later move to an edge worker writing to a queue without changing anything behind it. |
| **Inbox** | Durable log of raw provider events. Unique on `(provider, event_id)`. | Daily partitions, dropped after the retention window. Large bodies by reference. |
| **Router** | Looks up `(provider, routing_key)` in the subscription index, fans out to consumers. | Index lookup, O(1). Serialised per subscription (no overlapping) to keep ordering. |
| **Adapter pipeline** | Normalise to the canonical envelope, apply cheap filters, expand only for matching consumers, cache expansion across consumers. | One expansion per item, not per trigger. |
| **Poller** | Executes due polls from an index; one fetch per `(credential, resource)`; adaptive interval. | Claims work in batches with `SKIP LOCKED`. |
| **Reconciler** | Converges actual provider state to desired state, idempotently, with backoff. | Per-subscription lock; safe to run anywhere. |
| **Renewal scheduler** | Finds subscriptions due for renewal using an index on `renew_at`, with jitter. | Index query, never a scan. |
| **Governor** | Token buckets per provider app, per credential and per workspace. Shared by polls, expansions and reconciler calls. | Redis-backed. Prevents one tenant starving others. |
| **Health** | Computes subscription and trigger status, drives alerts, flips to fallback polling. | Event-driven plus periodic. |

### 4.2 Why an inbox
- Providers have strict ack deadlines (Slack: 3 s **(verify)**). We acknowledge fast and process later.
- Provider retries and our own replays become harmless: the inbox is the dedupe point.
- We can replay from the inbox after a bug fix, and run shadow comparisons against polling.
- It decouples ingest spikes from run capacity.

### 4.3 Thin events (privacy and cost)
Store the minimum needed to fetch the item (ids, cursor, headers), not message bodies. Content is fetched at fire time through the user's own token. This keeps customer data out of our database, meets the limited-use rules that Google and Slack apply, and keeps the inbox small. Providers that only send full payloads (Stripe, GitHub) are stored but masked and expired early.

---

## 5. Scalability design

### 5.1 Share everything that can be shared

**Subscription sharing.** The unit of provider registration is a **resource subscription**, keyed by `(provider, credential, resource_key, event_class)`. Many triggers link to it through a consumer table. Examples: one Gmail watch per mailbox, one Slack subscription per team (events arrive for all channels, filtering happens in our router), one repo hook per repo and event set.

- Reference counted. The last consumer leaving starts a short grace period (about 10 min) before unsubscribe, so republishing doesn't thrash the provider.
- When a new trigger needs a wider event set than the existing hook has, the reconciler upgrades the hook in place.

**Poll sharing.** Triggers on the same `(credential, resource)` share one fetch. Results fan out; each consumer applies its own filters and cursor position.

**Expansion sharing.** If three triggers match one Gmail message, fetch it once (short-lived cache keyed by `provider:item_id:version`).

### 5.2 Never scan
- Poll work comes from `poll_schedule(next_due_at)` with an index, claimed in batches: `SELECT … FOR UPDATE SKIP LOCKED LIMIT n`. Replaces `queueDuePolls()` scanning every trigger.
- Renewals come from an index on `subscriptions.renew_at`.
- Schedules already use an indexed `next_run_at`; keep it.
- Routing is a single indexed lookup on `(provider, routing_key)`.

### 5.3 Adaptive polling
- Interval per resource adapts: active resources poll at the minimum their plan allows; idle ones back off (for example up to 4x, capped at 15 min), and snap back on activity.
- Conditional requests wherever supported (ETag, `If-Modified-Since`, sync tokens, `delta` links) so an empty poll costs almost nothing.
- Jitter on every schedule so polls and renewals never align into a thundering herd.
- Plan-based floors: Free 15 min, paid 1 min **(decision, see §18)**. Enforced server-side.

### 5.4 Queues and isolation
- Separate Horizon queues and supervisors: `triggers-ingest` (tiny jobs), `triggers-route`, `triggers-poll`, `triggers-reconcile`, existing `triggers-event`.
- Ordering: serialise per subscription with a lock keyed on subscription id; no global ordering is attempted.
- Fairness: per-workspace concurrency (already built) plus weighted dequeue so one tenant's burst cannot starve the rest.
- Backpressure: if the route queue lag passes a threshold, ingress keeps accepting (inbox is cheap) while pollers slow down first.
- Provider isolation: a Slack outage must not stall Gmail. Each provider has its own governor budget and breaker.

### 5.5 Storage
- Inbox: Postgres table partitioned by day, dropped after retention (default 7 days). Bodies over a size threshold go to object storage with a reference.
- `trigger_events` keeps its current role (the per-trigger record), pruned as today. Polling dedupe ids are kept compact as they are now.
- Hot lookups live in indexed tables; Redis only holds caches, locks and token buckets, so a Redis loss costs speed, not correctness.

### 5.6 Capacity model (replace assumptions with data)

Assumptions: 100 000 active app triggers; 60 % on push sources; 40 % on polling.

| Quantity | Naive (per-trigger polling, 1/min) | This design |
|---|---|---|
| Polls per second | about 1 667 | Shared by resource and adaptive: assume 4:1 sharing and 3x back-off on idle: about 45 |
| Provider calls for push triggers | 1 667 | Near zero, plus hourly reconcile polls |
| Renewals per day | n/a | one per subscription per ~24 h, jittered: 40 000 / 86 400 ≈ 0.5 per second |
| Inbox writes | n/a | assume 10 M events/day ≈ 115/s average, 1 000/s burst; thin rows about 0.5 KB, about 5 GB/day before the 7-day drop |

Rule of thumb: provider calls scale with distinct watched resources; our database writes scale with events; neither scales with trigger count directly.

### 5.7 Growth path
1. Start inside the monolith with dedicated routes, queues and supervisors.
2. When ingest passes a few hundred requests per second, move the ingress controller to a stateless edge worker that verifies and enqueues; the inbox contract does not change.
3. When one provider dominates, give it its own worker pool and database partition set.

---

## 6. Data model

New tables (names indicative). All carry `workspace_id` where tenant-owned.

**`provider_apps`** (platform configuration, per environment)
`provider`, `client_id`, `signing_secret` (enc), `topic`/endpoint config, `status`, `limits`. One row per provider app per environment so staging never touches production apps.

**`subscriptions`** (shared resource subscriptions)
`id`, `workspace_id`, `provider`, `credential_id`, `resource_key`, `event_class`, `mechanism` (P1..P4), `external_id`, `routing_key` (indexed), `secret` (enc), `cursor` (json: history id, sync token, since), `expires_at`, `renew_at` (indexed), `status`, `consumer_count`, `last_event_at`, `last_ok_at`, `last_error`, `error_count`, `version`.
Unique: `(provider, credential_id, resource_key, event_class)`.

**`subscription_consumers`**
`subscription_id`, `trigger_id`, `filters` (json), `mode`, `start_from`, `created_at`. The link that makes sharing and ref counting work.

**`desired_subscriptions`**
What each trigger **wants**, computed at publish from the node's config. The reconciler diffs this against `subscriptions`. Publishing never calls the provider.

**`provider_events`** (inbox, partitioned by day)
`id`, `provider`, `routing_key`, `event_id` (unique with provider), `event_type`, `occurred_at`, `received_at`, `payload` or `payload_ref`, `status` (received, routed, dropped), `attempts`.

**`poll_schedule`**
`subscription_id`, `next_due_at` (indexed), `interval_seconds`, `idle_streak`, `claimed_until`.

**`trigger_samples`**
Last N sanitised payloads per trigger for the editor's field mapper.

**`triggers` additions**
`mode` (created / updated / deleted / any), `start_from` (now / beginning / date), `delivery` (computed: instant / polling / degraded), `source_version`.

**Canonical item key (important).** Every item gets `canonical_key = "{provider}:{resource}:{item_id}[:{version}]"`. Push and poll must produce the same key. That is what makes dual delivery safe (both can run; one wins) and what lets us switch a trigger from polling to push without re-firing old items.

---

## 7. Source SDK (contract)

One class per source type, registered in a `TriggerSourceRegistry`. The existing `SupportsTriggerMode` readers become the polling half of their source (no rewrite).

```php
interface TriggerSource
{
    public function key(): string;                    // 'slack.message'
    public function capabilities(): SourceCapabilities; // push, delta, backfill, ordering, ttl, limits, scopes

    // Control plane
    public function desired(Trigger $t): array;       // subscriptions this trigger needs
    public function subscribe(Subscription $s): SubscribeResult;
    public function renew(Subscription $s): RenewResult;
    public function unsubscribe(Subscription $s): void;
    public function verifyAccess(Credential $c, array $config): AccessCheck; // used at publish and by UI

    // Data plane
    public function verify(Request $r): VerifiedIngress;   // signature, handshake
    public function routingKeys(array $payload): array;
    public function normalise(array $raw): array;          // list of Envelopes
    public function poll(Subscription $s, ?Cursor $c): PollResult; // items + next cursor
    public function expand(Envelope $e, Credential $c): Envelope;  // only when needed
    public function canonicalKey(Envelope $e): string;

    // Product
    public function sample(Trigger $t): array;
    public function outputSchema(array $config): array;
    public function modes(): array;
    public function filters(): FilterSpec;
}
```

**Envelope** (CloudEvents-like): `id`, `source`, `type`, `subject`, `occurred_at`, `received_at`, `canonical_key`, `version`, `data`, `trace_id`. The `data` shape is the documented output schema; changing it creates a new `source_version` and existing triggers stay on theirs.

**Capability flags** let shared code behave correctly without provider conditionals: `supportsPush`, `supportsDelta`, `maxLookback`, `subscriptionTtl`, `renewalMargin`, `ordering`, `rateClass`, `requiresExpand`, `scopesNeeded`.

---

## 8. Control plane

### 8.1 Desired-state reconciler
Publish records what is wanted and returns immediately. The reconciler runs the diff:

- missing subscription: create (or link to an existing shared one),
- extra subscription: unlink, and delete after the grace period,
- changed resource or event set: upgrade in place,
- failed or expired: recreate or renew.

Properties: every provider call is idempotent (check existence by our id first), runs under a per-subscription lock, uses exponential backoff with a cap, and never blocks publish. The UI shows `Connecting` until the first confirmation.

### 8.2 Subscription state machine

```
pending ─▶ active ─▶ renewing ─▶ active
   │         │  ╲
   │         │   ╲─▶ degraded (push silent or renew failing; polling fallback ON) ─▶ active
   │         ╲
   │          ╲─▶ reconnect_needed (token revoked, scope missing) ─▶ pending (after reconnect)
   ╲─▶ failed (permanent: resource gone, permission denied)
 any ─▶ removing ─▶ removed
```

Transitions write an audit record and notify the owner when the trigger is affected.

### 8.3 Renewal
- `triggers:renew-subscriptions` every 5 minutes, index query on `renew_at <= now + margin`.
- Margin per provider (for example renew at 50 % of TTL, never later than TTL minus 6 h).
- Jittered `renew_at` after each renewal.
- Renewal failure retries with backoff; after N failures the subscription becomes `degraded` and polling takes over before expiry.

### 8.4 Reconciliation and garbage collection
- Daily `triggers:reconcile`: list the provider's view per credential and compare.
  - Orphans (exist remotely, not in our DB): delete.
  - Ghosts (in our DB, gone remotely): recreate.
- Handles workspace deletion, credential deletion, trigger clone, template install (installed triggers are always created **off**), and ownership transfer.

### 8.5 Credentials
- Revoked or expired token: subscription goes `reconnect_needed`; a banner appears on the trigger and in Apps; after reconnect the cursor backfills the gap.
- Scope upgrade needed for a new trigger type: ask only when that trigger is turned on.
- Creator leaves: the acting-user fallback already built applies; subscriptions belong to the credential, not the person.

---

## 9. Event semantics

### 9.1 Delivery guarantees
- Into the inbox: at-least-once (providers retry; we dedupe on `(provider, event_id)`).
- Out to runs: effectively once, via `canonical_key` dedupe at the trigger (existing `delivery_id`).
- Ordering: best effort per subscription (serialised processing; oldest first within a batch). Run input carries `occurred_at` so flows can sort. Global ordering is not promised.

### 9.2 Modes and start point
- **Mode:** `created`, `updated`, `deleted`, `any`. Updated mode compares the item's `version` (updated_at, etag, hash) to the last one fired and never fires for an item that was created in the same window.
- **Start from:** `now` (default, baseline as today), `beginning` (backfill with a cap and a confirmation), or a date.
- Changing mode or start point on a live trigger does not re-fire old items; it only moves the boundary.

### 9.3 Filters (evaluated before any expensive call)
- Declarative filter spec per source (Slack: channel, ignore bots, ignore replies, emoji; Gmail: label, from, subject contains, has attachment; GitHub: event, action, branch; and so on).
- Plus the generic "Only run when" conditions already built.
- Evaluate on the normalised envelope first; expand only for consumers that pass.

### 9.4 Transform and delivery options per trigger
- **Debounce:** wait for quiet N seconds, then fire once with the last event.
- **Batch / digest:** collect events for N minutes, fire one run with a list.
- **Rate limit:** at most N runs per minute from this trigger (token bucket); excess waits, not dropped.
- **Burst control:** works alongside the concurrency caps already built.

### 9.5 Loop and storm protection
- Never fire on events authored by our own app (Slack `app_id`/`bot_id`, Gmail messages sent by the account when "ignore my own" is on, GitHub events from our app).
- Storm detector: if a trigger's fire rate exceeds a multiple of its trailing baseline, throttle it, then pause and notify.
- Run lineage depth: a run started from an event that a previous run of the same trigger caused is counted; past a limit, stop and notify.

---

## 10. Failure modes and responses

| Failure | Detection | Response |
|---|---|---|
| Provider retries or duplicates | Unique inbox key | Ignore; count `duplicate_count`. |
| Our ingress down | Provider retries; inbox gap | Provider retry covers short outages; change-feed cursor and reconciliation poll fill longer ones. |
| Watch or subscription expired | `renew_at` passed, no events | Renew; if failing, `degraded` plus polling. |
| Push silently stops | No events for N hours on an active account, or canary missed | Probe with a poll; if events found, mark `degraded`, repair the subscription. |
| Cursor too old (Gmail history, sync token invalid) | 404 / 410 from provider | Bounded full resync; record the gap in the event log. |
| Token revoked | 401 / `invalid_grant` | `reconnect_needed`, notify, pause fetching. |
| Provider 429 | Response | Governor backoff using `Retry-After`; never counts toward the trigger breaker immediately. |
| Provider outage | Error rate per provider | Provider-level breaker; pause that provider's polls and renewals; resume with jitter. |
| Burst larger than a poll window | Window full of unseen items | Page until a known item is reached; if a hard cap is hit, record a visible "gap" marker. |
| Poison event (always fails) | Attempts exceeded | Move to dead-letter, keep for replay, do not block the subscription. |
| Workspace suspended | Router check | Drop to skipped, no streak (already built). |
| Redis lost | Health check | Locks and buckets rebuild; correctness unaffected (state is in the database). |
| Bad deploy of an adapter | Error spike, canary | Feature flag off; replay from the inbox after the fix. |
| Reconciler bug creates duplicate hooks | Reconciliation job | Orphan cleanup; unique constraint prevents most. |

---

## 11. Security and compliance

**Ingress verification matrix** (each is mandatory, verified before parsing):

| Provider | Check |
|---|---|
| Slack | HMAC v0 over `v0:timestamp:body`, 5-minute tolerance **(verify)** |
| GitHub | `X-Hub-Signature-256` HMAC of raw body |
| Stripe | `Stripe-Signature` with timestamp tolerance |
| Gmail (Pub/Sub push) | Signed OIDC JWT, audience and service account checked |
| Microsoft Graph | Validation token handshake, then `clientState` secret per subscription |
| Google Drive/Calendar channels | Channel id and token (our secret) in headers |
| Linear, Typeform, Shopify, HubSpot, Monday | Provider HMAC or signature header per docs **(verify)** |

Other rules:
- Secrets per subscription, encrypted; rotated on re-subscribe.
- Tenant isolation: an event can only reach consumers linked to the subscription its routing key resolves to; a test asserts a cross-tenant routing key finds nothing.
- Least privilege: request scopes per trigger type, at activation time.
- Payload hygiene: thin events, masking of tokens and credential headers, retention window, deletion on workspace or account removal.
- Provider policy fit: Google API user-data and limited-use rules, Slack data rules. Keep a short compliance note per provider in this repo.
- Abuse: ingress rate limits per source IP and per routing key; payload size caps; custom-poll URLs go through the existing SSRF guard.
- Audit: subscription create, renew failure, reconnect, delete, and manual overrides are logged with actor.

---

## 12. Product and UX specification

### 12.1 Status model shown on every trigger
`Listening (instant)` · `Checking every N min` · `Connecting…` · `Renewing` · `Degraded (using fallback)` · `Reconnect account` · `Paused` · `Off after failures` · `Waiting for plan upgrade`. Each has a one-line explanation and one button (reconnect, retry, view log).

### 12.2 Setup flow
1. Pick source and event ("Slack: new message in a channel").
2. Pick account; show exactly what access will be requested.
3. Pick resource (channel, label, folder, repo) with search.
4. **Access check:** the source's `verifyAccess()` confirms the app can see it (for Slack: the bot is in the channel, with a one-click "add it" hint).
5. **Fetch sample:** show recent real items; map fields from them.
6. **Listen for test event:** a temporary subscription (TTL about 10 min) streams the next real event to the editor over Reverb.
7. Choose mode and start point; set advanced options (debounce, batch, rate limit).
8. Publish; the trigger shows `Connecting…` then `Listening`.

### 12.3 Operate
- **Event log per trigger:** received, filtered (with the reason), skipped, fired, failed; payload preview; **replay** and **replay as test**.
- **Health card:** last event, last successful check, next renewal, lag, error history.
- **Notifications:** reconnect needed, degraded, auto-paused, storm detected, quota reached.
- **Templates:** always install triggers off, with a "turn on" checklist.

### 12.4 Admin (super-admin console)
Subscriptions by provider and status, expiring soon, renewal failures, lag per provider, per-workspace trigger load, force renew or resubscribe, provider kill switch, replay window tool.

---

## 13. Plans, limits and cost

| Lever | Proposal (decision, see §18) |
|---|---|
| Active triggers | Existing `active_triggers` limit; push triggers count the same as polling ones. |
| Polling floor | Free 15 min, Starter 5 min, Pro 1 min. Push is not interval-limited. |
| Event quota | Soft monthly event allowance per plan; over it, slow down and notify, never drop silently. |
| Fair use | Per-workspace governor budgets and burst caps so one tenant can't exhaust a provider app's quota. |
| Run cost | Credits per run unchanged. Polling and ingest are platform cost, not billed. |
| Cost visibility | Admin dashboard of provider calls per workspace to spot abuse early. |

---

## 14. Observability

**Metrics** (per provider, per source): ingress rate and latency; inbox size and age; route lag; poll duration and lag; renewals due / done / failed; subscriptions by status; duplicate rate; filter drop rate; dead-letter count; end-to-end latency histogram (`occurred_at` to run start).

**Tracing:** one `trace_id` from ingress through inbox, router, event and run, shown in the run log.

**Synthetic canaries:** a dedicated test account per provider produces a known event every few minutes; a canary trigger must start a run within the SLO, otherwise alert. This is the most reliable detector of silent push failure.

**Alerts:** subscription within 24 h of expiry and not renewed; renewal failure rate; push silent beyond threshold for an active account; poll lag above target; inbox or route backlog; provider breaker open; canary missed.

**Dashboards:** Pulse cards for ingest and lag; super-admin pages for subscriptions and workspaces.

---

## 15. Testing strategy

- **Unit:** adapters (normalise, canonical key, filters, expand) from recorded payload fixtures.
- **Contract tests per source:** subscribe, receive, duplicate, out of order, renew, expire, unsubscribe, revoked token, cursor invalid. Run against provider simulators in CI.
- **Simulators:** local fake servers for Slack, Google (Pub/Sub, Drive, Gmail history), Graph, GitHub that can drop, duplicate, delay and reorder deliveries.
- **Property tests:** dedupe and ordering invariants (no event fires twice, none is lost) under random delivery orders.
- **Integration:** full path from ingress to run with real queues in CI.
- **Load:** ingest at 10x expected burst; reconciler with 100 000 subscriptions; renewal storm; poll scheduler claiming with many workers.
- **Chaos (staging):** kill workers mid-batch, expire tokens, block provider hosts, clock skew.
- **Tenant isolation:** automated cross-tenant routing tests.
- **Live smoke (staging, pre-release):** real Slack and Google test accounts, via the canaries.

---

## 16. Rollout and migration

1. **Flags** per provider and per workspace; global and per-provider kill switches (`TRIGGERS_NODES_ENABLED` style).
2. **Shadow mode:** for a trigger already polling, create the push subscription too, but have push **record only** ("would have fired") and compare with polling for a week. Exit when the match rate and latency meet the targets.
3. **Dual delivery:** both paths on; `canonical_key` dedupe ensures one run. Polling interval relaxes to the reconciliation rate once push is proven.
4. **Cut over:** polling becomes the fallback only. Keep the old path one release for rollback.
5. **Migration of existing triggers:** nothing changes in published graphs. `TriggerNodeSync` keeps creating the `triggers` row; a new step records `desired_subscriptions`. Existing `delivery_id` values (for example Slack `ts`) are mapped to the new `canonical_key` so already-seen items are not re-fired.
6. **Rollback:** turn the provider flag off; triggers fall back to polling immediately; the inbox keeps data for replay.

---

## 17. Roadmap

Assumes two engineers, one of them mostly on triggers. External lead times (Google review) run in parallel.

| Phase | Scope | Exit criteria | Est. |
|---|---|---|---|
| **0 Foundation** | Tables, source registry and SDK, ingress route group, inbox with partitions, router, reconciler skeleton, renewal and reconcile commands, status model, `poll_schedule` replacing the per-minute scan, wrap the 8 readers as polling sources, canonical keys, metrics | No behaviour change for users; poll scheduling is index-driven; contract tests green; load test of scheduler passes | 3 to 4 wks |
| **1 Test and UX** | Fetch sample, listen for test event (Reverb), sample store, status chips, event log with replay, start-from and mode, access check | A new user maps fields from a real sample with no waiting; support questions about "why didn't it fire" answerable from the log | 2 to 3 wks |
| **2 Slack (P1)** | Events API source for messages and reactions, shared team subscription, filters, loop guard, bot-in-channel check, reaction trigger | Shadow match above 99.5 %, p95 latency under 5 s, canary green for 7 days | 2 wks |
| **3 Google (P3)** | Gmail watch plus history cursor, Drive changes, Calendar sync, Sheets diff, "before event" trigger | Same exit bar; renewal automation proven over 2 expiry cycles; Google verification submitted at the start of this phase | 4 to 5 wks plus review time |
| **4 Microsoft (P3)** | Graph subscriptions with renewal, lifecycle notifications, delta catch-up (Outlook, later Teams) | Same exit bar | 2 to 3 wks |
| **5 Developer tools (P2)** | GitHub (repo hooks and App), Linear, Jira, Stripe, Typeform, Shopify, Monday; shared hook lifecycle helper | Each source passes the contract suite with a simulator | 2 wks for the first, then about 3 days each |
| **6 Advanced delivery** | Debounce, batch / digest, per-trigger rate limits, storm detector, plan-based floors and quotas, admin tools | Storm and quota scenarios covered by tests | 2 to 3 wks |
| **7 Breadth and AI monitors** | HubSpot, Notion, Airtable, Salesforce, Zendesk; guard-railed "Create with AI" polling triggers | Governor and sandbox limits verified | ongoing |

Critical path: Phase 0, then Slack proves the architecture, then Google (longest external dependency).

---

## 18. Decisions to confirm (ADR candidates)

| # | Decision | Recommendation |
|---|---|---|
| 1 | Slack: one platform app, or bring-your-own app too? | One platform app first; BYO later for enterprise. |
| 2 | Gmail: commit to Google verification and the yearly security assessment? | Yes; start in Phase 0 because lead time is weeks. |
| 3 | Inbox store: Postgres partitions or a log system (Kafka, Redis streams, SQS)? | Postgres partitions now; revisit above a few hundred events per second. |
| 4 | Ingress: monolith or edge worker? | Monolith with a clean boundary; extract on need. |
| 5 | Thin events (fetch at fire time) or full payload storage? | Thin by default; full only for providers that require it, masked, short retention. |
| 6 | Polling floors by plan | Free 15, Starter 5, Pro 1 minute. |
| 7 | Does push count against the active-trigger limit? | Yes, same limit. |
| 8 | Default start point | Now (baseline). |
| 9 | Do shared subscriptions cross workspaces (for platform-app events)? | Never share across workspaces; share only within one workspace and credential. |
| 10 | Dev environments need a public HTTPS URL | Standard tunnel plus per-developer provider apps. |

---

## 19. Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Google restricted-scope review delays Gmail | Gmail stays on polling | Start in Phase 0; ship Slack and Microsoft first; keep polling path healthy. |
| Provider changes limits or policy (for example Slack read limits for non-marketplace apps) | Polling degrades | Prefer push; track provider changelogs; kill switches per provider. |
| Subscription drift or silent push failure | Missed events | Canaries, reconciliation polls, `degraded` fallback, daily reconcile. |
| Reconciler bug floods a provider | Quota loss, bans | Idempotency, governor budgets, per-provider breaker, staged rollout. |
| Thundering herd on renewals or after outage | Self-inflicted outage | Jitter everywhere; resume with ramp-up. |
| Inbox growth | Cost | Thin events, daily partitions, short retention, size alerts. |
| Over-engineering before demand | Time | Phase gates tied to exit criteria; Phase 0 keeps behaviour identical. |
| Customers confused by many states | Support load | Few user-visible states with one action each; event log answers "why". |

---

## Appendix A. Provider matrix (to verify before each phase)

| Provider | Mechanism | Lifetime / renewal | Verification | Catch-up | Watch for |
|---|---|---|---|---|---|
| Slack | P1 Events API (`message.*`, `reaction_added`) | No expiry | HMAC signature + timestamp; URL challenge | `conversations.history` (limited **(verify)**) | Bot must be in channel; 3 s ack; retry headers; `event_id` dedupe |
| Gmail | P3 `users.watch` to Pub/Sub, `history.list` | About 7 days; renew daily | OIDC JWT on push | `history.list` from stored `historyId`; full sync if too old | One watch per mailbox; restricted scopes |
| Outlook / Teams | P3 Graph subscriptions + delta | Mail about 3 days max **(verify)**; renew often | Validation token + `clientState` | Delta query | Lifecycle notifications; per-mailbox limits |
| Google Drive | P3 `changes.watch` | Short-lived channels **(verify)** | Channel id + token | `changes.list` from page token | Notification has no body; `sync` message first |
| Google Calendar | P3 `events.watch` | Channels expire **(verify)** | Channel id + token | Sync token (410 means full resync) | Series and exceptions |
| Google Sheets | P3 via Drive file channel, then the trigger's own poll reads the rows | Per Drive channel | As Drive | Stored row hashes | No row-level push |
| GitHub | P2 repo hook, or P1 via GitHub App | No expiry; disabled after repeated failures | `X-Hub-Signature-256` | Redelivery API, list recent deliveries | Needs admin rights for repo hooks |
| Linear | P2 webhooks | No expiry | HMAC header | Poll recent issues | Admin needed to create |
| Jira Cloud | P2 webhooks | Dynamic webhooks expire (about 30 days **(verify)**), refresh | Per docs | JQL poll | Refresh job needed |
| HubSpot | P1 app webhooks | No expiry | v3 signature | Poll by modified date | Batched events |
| Stripe | P2 endpoints | No expiry | Signature + tolerance | Events API list | Connect accounts |
| Typeform (built), Shopify, Monday | P2 webhooks | No expiry (Monday: challenge handshake) | HMAC / challenge | Poll responses / orders / boards | Shopify mandatory topics |
| Airtable | P3 webhooks (ping, then fetch payloads by cursor) | About 7 days unless refreshed **(verify)** | MAC | Payload cursor | Refresh job needed |
| Notion | P1 integration webhooks (event says what changed; the poll reads it) | No expiry | `X-Notion-Signature` HMAC with the verification token | Search / database query by edit time | Events are batched, so seconds to a minute late |
| Salesforce, Zendesk | P4 (check push options at build time) | n/a | n/a | `last_edited` / modified filters | Page size caps |

## Appendix B. Repository layout (proposed)

```
app/Triggers/
  Sources/            Slack/, Google/, Microsoft/, GitHub/, ...   (one class per source type)
  Contracts/          TriggerSource, SourceCapabilities, Envelope, Cursor
  Ingress/            ProviderIngressController, Verifiers/
  Inbox/              InboxWriter, InboxRouter, Pruner
  ControlPlane/       Reconciler, SubscriptionStateMachine, RenewalScheduler, Reconciliation
  Polling/            PollScheduler, SharedPoller, AdaptiveInterval
  Governor/           TokenBuckets, ProviderBreaker
  Delivery/           Debounce, Batcher, RateLimiter, StormDetector
app/Console/Commands/Triggers/   renew-subscriptions, reconcile, claim-polls, prune-inbox
tests/Triggers/       Simulators/, Contracts/, Fixtures/, Load/
```

New code lives under `app/Triggers` only if the team agrees to a new base folder; otherwise map each group into the existing `Services/Triggers`, `Jobs/Triggers` and `Console/Commands/Triggers` folders.

## Appendix C. Mapping onto the current code

| Today | Becomes |
|---|---|
| `SupportsTriggerMode` readers | The `poll()` / `expand()` half of each source |
| `PollTrigger` | A job that runs a shared poll for a subscription, not for one trigger |
| `RunDueTriggersCommand::queueDuePolls` | `triggers:claim-polls` reading `poll_schedule` |
| `TriggerNodeSync` | Also writes `desired_subscriptions` |
| `TriggerService::receive` | Unchanged; the router calls it per consumer |
| `AssistantSlackEventsController` | Shares its verifier with the new Slack ingress |
| `trigger_events.delivery_id` | Becomes the `canonical_key` |
| Status in `NodeFlowTriggerToggle` | Reads the computed status from the subscription state machine |
