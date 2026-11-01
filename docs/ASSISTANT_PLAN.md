# Personal Assistant Plan (Gumball-style, fully separate from Agents)

## Context

Gumloop ships **Gumball**: a personal agent every user gets automatically. It chats over web, Slack DM, email and SMS; it uses the user's own connectors and every skill they can reach; it runs background products (Daily Chew with Situations, Smart Inbox, Meeting Prep); and it sits on a heavy runtime (100–200 step runs, context compaction, steering, a code sandbox, Brain search, versioned/hosted artifacts). Sources: `docs/gumloop/output/raw/core-concepts/gumball.md` (older mirror) plus the live docs at docs.gumloop.com (`gumball`, `text_gumball`, `agents`, `agent_sandbox_and_secrets`, `agent_artifacts`, `brain`, `agent_triggers`).

**Decision: the Assistant is its own domain.** It does not reuse `Agent`, `AgentSession`, `AgentRunner`, `ToolRegistry`, `SkillInjector` or `Approvals/*`. Custom agents and the Assistant evolve and ship independently; neither can break the other. The cost is a second runtime (loop, tool gate, approvals, streaming) that we own and test separately.

**Shared platform services (not agent logic, called directly):** `User`/`Workspace`/membership, `Connector`/`ConnectorCredential` + `OAuthConnectorFlowService`, the Gmail/Calendar/Slack/Drive/Docs/Sheets/GitHub HTTP clients behind the integration nodes, `ModelCatalogResolver`, `CreditMeter`/`CreditGate`/`PlanLimitGate`, `NotificationDispatcher`, `Secrets` vault, Reverb, Horizon. If a later decision wants zero sharing here too, only the connector clients need copying.

Working name in code: **Assistant** (`App\…\Assistant`). The product/brand name is a UI string only (`config('assistant.display_name')`).

---

## 0. Ground rules

- One Assistant per (`workspace_id`, `user_id`). Created lazily on first open. **Owner-only**: no sharing, no admin view of content.
- Every tool carries an `effect`: `read | write | external | destructive`. Background runs (briefings, inbox, meeting prep) are **read-only by construction**: write tools are never handed to the model, including tools reached through a sub-call.
- Every background run is **idempotent**: a unique run key means a retry never produces a second delivery.
- Every source is tracked **independently**: one failing connector never blocks the rest, and its cursor never advances on failure.
- All LLM usage goes through `CreditMeter` with new `CreditTransactionType` cases (`assistant_turn`, `assistant_briefing`, `assistant_inbox`, `assistant_meeting_prep`, `assistant_sandbox`, `assistant_brain`).
- Permissions cascade: `assistant.access` → `assistant.daily`, `assistant.inbox`, `assistant.meeting_prep`, `assistant.sms`. Custom roles default the children to off. Losing `assistant.access` turns Smart Inbox off and it does not auto-resume.

---

## 1. Schema

All tables are prefixed `assistant_` so the domain stays isolated. Integer PKs like the rest of the app.

### Core
```
assistants
  id, workspace_id FK, user_id FK, display_name nullable, model_catalog_id nullable,
  instructions text nullable            -- user-editable standing instructions
  settings json                          -- step_budget, web_search_provider, timezone, etc.
  inbound_email_token string unique      -- for <token>@in.<domain> routing
  timestamps, softDeletes
  unique(workspace_id, user_id)

assistant_sessions
  id, assistant_id FK, title nullable, status (active|running|paused|archived),
  origin (web|slack|email|sms|task|briefing), external_thread_ref nullable
  incognito bool default false, expires_at nullable,
  context_tokens int default 0, last_activity_at, timestamps
  index(assistant_id, last_activity_at)
  unique(assistant_id, origin, external_thread_ref)

assistant_messages
  id, session_id FK, role (user|assistant|tool|recap), content longText,
  tool_calls json nullable, tool_call_id nullable, attachments json nullable,
  usage json nullable, compacted_into_id nullable FK self, timestamps
  index(session_id, id)

assistant_turns                           -- one row per run of the loop
  id, session_id FK, status (queued|running|awaiting_approval|completed|failed|cancelled),
  steps int, step_budget int, model string, trigger (user|queue|resume|briefing|channel),
  error text nullable, started_at, finished_at, usage json

assistant_queued_inputs                   -- steering while a turn runs
  id, session_id FK, turn_id nullable, content text, mode (redirect|queue),
  consumed_at nullable, timestamps
```

### Tools, approvals, trust
```
assistant_actions
  id, turn_id FK, session_id FK, tool string, arguments json, effect, risk (low|medium|high),
  status (pending|approved|rejected|executed|failed|expired), result json nullable,
  decided_at nullable, expires_at, timestamps

assistant_tool_rules                      -- "always allow / ask / deny" per tool or connector
  id, assistant_id FK, target_type (tool|connector), target string, rule (allow|ask|deny), timestamps
```

### Memory and personalization
```
assistant_memories
  id, assistant_id FK, content text, kind (fact|preference|person|project), source_session_id nullable,
  embedding vector nullable, last_used_at, timestamps

assistant_style_profiles                  -- the Tone and Design "skills"
  id, assistant_id FK, kind (tone|design), body text, version int, timestamps
  unique(assistant_id, kind)

assistant_feedback
  id, assistant_id FK, target_type (briefing_config|inbox_config|label|style), target_id,
  content text, applied_change json nullable, status (pending|applied|ignored), timestamps
```

### Briefings (Daily + Meeting Prep share one engine)
```
assistant_briefing_configs
  id, assistant_id FK, type (daily|meeting_prep), enabled bool, paused_at nullable,
  schedule json            -- daily: {time, days[], timezone}; meeting_prep: {minutes_before, scope}
  connector_scope (all|selected), connector_credential_ids json (<=50),
  instructions text (<=4000), model_catalog_id nullable,
  delivery json            -- {email:bool, slack_dm:bool, sms:bool}
  settings json            -- meeting_prep: {calendar_credential_id, calendar_id, auto:bool}
  unique(assistant_id, type)

assistant_briefing_runs
  id, config_id FK, run_key string unique, -- daily:{date}, meeting:{event_id}:{start}
  status (queued|collecting|writing|delivering|completed|failed),
  window_start, window_end, summary text, document longText,
  source_results json      -- [{credential_id, provider, ok, error, items}]
  delivery_results json, usage json, delivered_at nullable, timestamps

assistant_source_cursors
  id, config_id FK, connector_credential_id FK, cursor_at, cursor_token nullable, timestamps
  unique(config_id, connector_credential_id)

assistant_situations
  id, assistant_id FK, briefing_run_id nullable, title, summary text, next_step text nullable,
  citations json, status (open|sent|done|dismissed), session_id nullable, timestamps

assistant_situation_steps
  id, situation_id FK, position int, body text, status (todo|done|skipped)

assistant_meetings
  id, assistant_id FK, credential_id FK, provider_event_id, calendar_id, title,
  starts_at, attendees json, is_external bool, prep_status (none|scheduled|prepared|failed),
  briefing_run_id nullable, timestamps
  unique(assistant_id, provider_event_id, starts_at)
```

### Smart Inbox
```
assistant_inbox_configs
  id, assistant_id FK unique, provider (gmail|outlook), credential_id FK, enabled bool,
  classification_model_id nullable, drafting_instructions text nullable,
  draft_mode (confident|off), known_senders_only bool default true,
  skip_existing_labels bool, grounding_connector_ids json nullable,
  provider_state json      -- gmail historyId / watch expiry, outlook deltaLink / subscription id
  capabilities json        -- {can_draft, can_archive} from granted scopes / app rules

assistant_inbox_labels
  id, config_id FK, name, definition text, color, group (keep|move_out),
  builtin bool, enabled bool, provider_label_id nullable, position int
  unique(config_id, name)        -- max 25, builtins cannot be deleted

assistant_inbox_messages
  id, config_id FK, provider_message_id, thread_id, from_email, subject,
  received_at, labels_applied json, archived bool, skipped_reason nullable,
  draft_provider_id nullable, draft_hash nullable, suggestion text nullable, timestamps
  unique(config_id, provider_message_id)
```

### Channels
```
assistant_channel_links
  id, assistant_id FK, channel (slack|sms), external_id (slack user id / E.164 phone),
  external_workspace nullable, verified_at nullable, timestamps
  unique(channel, external_id, external_workspace)

assistant_phone_verifications
  id, user_id FK, phone, code_hash, attempts int, expires_at, timestamps
```

### Triggers (the Assistant's own, separate from workflow triggers)
```
assistant_triggers
  id, assistant_id FK, type (schedule|once|webhook|app_event), name,
  cron nullable, run_at nullable, timezone, webhook_token nullable unique,
  app_event json nullable, prompt_template text, model_catalog_id nullable,
  status (pending_approval|active|paused|disabled), consecutive_failures int,
  last_run_at nullable, created_by (user|assistant), timestamps
```

### Computer, artifacts, Brain (Track B/C)
```
assistant_sandboxes
  id, assistant_id FK, session_id nullable, provider (e2b|daytona|modal), provider_sandbox_id,
  status, last_used_at, timestamps

assistant_artifacts
  id, assistant_id FK, session_id nullable, name, mime, access (restricted|workspace|password|public),
  password_hash nullable, host_alias nullable unique, interactive bool, latest_version int, timestamps, softDeletes
  unique(assistant_id, session_id, name)

assistant_artifact_versions
  id, artifact_id FK, version int, disk_path, size, thumbnail_path nullable, data_script text nullable, timestamps

assistant_brain_sources
  id, assistant_id FK, provider, credential_id nullable, config json, scope (personal),
  last_synced_at nullable, sync_cursor nullable, status, timestamps

assistant_brain_documents
  id, source_id FK, external_id, title, url, acl json nullable, content_hash, synced_at
  unique(source_id, external_id)

assistant_brain_chunks
  id, document_id FK, position, content text, embedding vector, tsv (full-text index)
```

---

## 2. Backend layout

```
app/Models/Assistant/                 one model per table above
app/Enums/Assistant/                  SessionStatus, TurnStatus, ToolEffect, ToolRisk, ActionStatus,
                                      BriefingType, BriefingRunStatus, SituationStatus, StepStatus,
                                      InboxProvider, LabelGroup, DraftMode, ChannelType, TriggerType, …
app/Policies/AssistantPolicy.php      owner-only, plus permission checks
app/Ai/Assistant/
  AssistantAgent.php                  Laravel AI agent (Conversational, HasTools); MaxSteps from settings
  BriefingWriterAgent.php             structured output: summary, sections, situations, citations
  MeetingBriefAgent.php
  InboxClassifierAgent.php            structured: labels[{name, confidence}]
  ReplyDrafterAgent.php               structured: {should_draft, confident, body, reason}
  CompactorAgent.php                  structured recap of old messages
  StyleLearnerAgent.php               proposes Tone/Design edits
  ScheduleParserAgent.php             natural language → cron + timezone
  ThreadRouterAgent.php               SMS: continue vs new task
  Tools/                              see §3
app/Services/Assistant/
  AssistantProvisioner.php
  Runtime/
    AssistantLoop.php                 open turn → build context → run → settle/pause
    ContextBuilder.php                instructions + style + memories + recap + recent messages
    ContextCompactor.php
    ContextMeter.php
    InputQueue.php                    redirect/queue consumption between steps
    TurnLock.php                      one running turn per session
    StepBudget.php
  Tools/
    ToolCatalog.php                   builds the tool list for a turn (mode: chat|read_only)
    ToolGate.php                      effect + rules → allow | ask | deny
    ApprovalService.php               create/decide/expire actions, resume turn
    ConnectorToolFactory.php          wraps connector clients as tools, tagged with effect
  Skills/SkillSearch.php              searches every Skill the user can access (read-only use of skills table)
  Memory/MemoryStore.php
  Personalization/StyleProfiles.php, FeedbackApplier.php
  Briefings/
    BriefingScheduler.php
    BriefingRunner.php
    SourceCollector.php               one per provider, read-only
    Collectors/{Gmail,GoogleCalendar,Slack,GoogleDrive,GitHub,Outlook,…}Collector.php
    CursorStore.php
    DeliveryRouter.php                in-app, email, slack_dm, sms, exactly once per run
  Meetings/CalendarSync.php, ExternalMeetingDetector.php
  Inbox/
    MailProvider.php (interface)      list new, get thread, apply labels, archive, draft, sync labels
    GmailProvider.php, OutlookProvider.php
    InboxIngestor.php                 history/delta → new messages
    InboxClassifier.php               classify, cap 5, drop low-confidence, keep-wins archive rule
    ReplyDrafter.php                  known-senders, skip rules, reply-all, never overwrite edited draft
    LabelSync.php                     adopt by name, rename on Gmail, register Outlook categories
  Channels/
    SlackDmHandler.php, InboundEmailHandler.php, SmsHandler.php
    ChannelReplyFormatter.php         markdown → Slack blocks / email HTML / plain SMS chunks
  Triggers/TriggerScheduler.php, TriggerRunner.php
  Sandbox/SandboxManager.php, SandboxDriver.php (interface), E2bDriver.php
  Artifacts/ArtifactStore.php, ArtifactHosting.php, InteractiveRunner.php
  Brain/BrainSearch.php, BrainSync.php, Syncers/{Drive,Gmail,Slack,GitHub,Upload}Syncer.php
app/Jobs/Assistant/
  RunAssistantTurnJob, RunBriefingJob, CollectSourceJob, DeliverBriefingJob,
  SyncCalendarJob, PrepareMeetingJob, IngestInboxJob, ClassifyEmailJob, DraftReplyJob,
  RenewInboxWatchJob, RunAssistantTriggerJob, SyncBrainSourceJob, CompactSessionJob,
  PurgeIncognitoSessionsJob, HandleChannelMessageJob
app/Events/Assistant/                 TurnStarted, TurnDelta, ToolCalled, ActionRequested, TurnFinished,
                                      BriefingCompleted, SituationCreated, InboxMessageClassified
app/Console/Commands/Assistant/       assistant:run-due-briefings, assistant:sync-calendars,
                                      assistant:run-due-triggers, assistant:renew-inbox-watches,
                                      assistant:brain-sync, assistant:expire-actions, assistant:purge-incognito
app/Http/Controllers/Api/Internal/V1/Assistant/
app/Http/Requests/Assistant/
app/Http/Resources/Assistant/
routes/api/internal/assistant.php
routes/webhooks.php                   + slack/events, email/inbound, sms/inbound, gmail/push, outlook/notify,
                                        assistant/hooks/{token}
config/assistant.php                  display_name, default_step_budget, compaction thresholds, limits
tests/Feature/Assistant/, tests/Unit/Assistant/
```

---

## 3. Runtime (Track A) — the core

### Turn lifecycle
1. `POST /assistant/sessions/{session}/messages` stores the user message, creates an `assistant_turns` row (`queued`) and dispatches `RunAssistantTurnJob` on a dedicated Horizon queue `assistant`. Returns `202` with the turn id.
2. If a turn is already running, the message goes to `assistant_queued_inputs` instead (`mode` from the request: `redirect` = inject at next step, `queue` = run after).
3. The job acquires `TurnLock` (cache lock per session), then `AssistantLoop`:
   - `ContextBuilder`: base instructions → user standing instructions → Tone/Design profiles → top-k memories → session recap (`role=recap`) → messages after the recap.
   - `ToolCatalog::for($assistant, mode: chat)`.
   - Runs `AssistantAgent` with streaming; every delta is broadcast as `TurnDelta` on `private-assistant.{userId}.sessions.{sessionId}`.
   - Between steps (tool-call boundary): check `InputQueue` for `redirect` inputs and append them; check cancellation flag; check `StepBudget` (default 100, max 200).
   - Tool call → `ToolGate`: `allow` executes, `deny` returns a refusal result, `ask` creates `assistant_actions` (pending), broadcasts `ActionRequested`, and **pauses** the turn (`awaiting_approval`) storing the provider state needed to resume.
4. Settle: persist assistant message + usage, charge credits, update `context_tokens`; if above 80% of the model window dispatch `CompactSessionJob`; then if any `queue` inputs exist, start the next turn.
5. Approval decision (`POST …/actions/decisions`) executes or rejects, then dispatches `RunAssistantTurnJob` with `trigger=resume`.

Why queued jobs instead of a held SSE request: 100+ step runs exceed request timeouts, and the same loop must serve Slack/email/SMS where there is no browser connection. The browser listens over Reverb; SSE is not needed.

### Context compaction
- Threshold 80% of the model's window (from `ModelCatalogResolver`), protect the most recent ~40k tokens.
- `CompactorAgent` writes a structured recap: goals, decisions, facts learned, open tasks, files/artifacts produced, pending approvals.
- Older messages get `compacted_into_id = recap.id` and are excluded from provider context but still shown in the UI.
- `GET /assistant/sessions/{id}/context` → `ContextMeter` breakdown: system, instructions, style, memories, tools, recap, conversation.

### Tool gate
| Effect | Chat default | Background runs |
|---|---|---|
| read | allow | allow |
| write | ask | removed from catalog |
| external (sends to people) | ask | removed |
| destructive | ask (high risk, always) | removed |

User rules in `assistant_tool_rules` override chat defaults (allow/ask/deny per tool or per connector). Destructive can never be set to `allow`.

### Built-in tools (`app/Ai/Assistant/Tools`)
| Tool | Effect | Notes |
|---|---|---|
| `search_skills`, `use_skill` | read | over every skill the user can access (workspace + team) |
| `remember`, `recall`, `forget` | write (self) | memory, auto-allowed |
| `web_search`, `web_fetch` | read | provider from config |
| `search_brain`, `read_brain_document` | read | Track C |
| `run_python`, `run_shell`, `read_file`, `write_file` | write (sandbox) | Track B, allowed by default inside sandbox |
| `export_artifact` | write (self) | versioned by name |
| `generate_image` | write (self) | Laravel AI `Image` |
| `create_trigger`, `list_triggers`, `pause_trigger`, `delete_trigger` | write | created as `pending_approval` |
| `update_inbox_preferences`, `update_briefing_instructions`, `update_style` | write (self) | used by the feedback loop |
| `spawn_task` | write (self) | parallel sub-task (max 10 concurrent, depth 1) running the same loop in a child session |
| connector tools | per tool | from `ConnectorToolFactory`, one per connector operation, e.g. `gmail_list_messages` (read), `gmail_send` (external), `gmail_delete` (destructive) |

### Other runtime features
- **Stop**: `POST …/turns/{turn}/cancel` sets a flag checked between steps.
- **Incognito**: `incognito=true`, `expires_at=+24h`, excluded from memory writes, style learning and Brain indexing; purged hourly.
- **Voice**: `POST /assistant/transcribe` (≤25 MB) → Laravel AI transcription → text only.
- **Attachments**: stored on the session; images passed to the model, documents parsed to text.
- **MCP** (later): user-added remote MCP servers become tools, each tagged with effect by the user (default `ask`).

---

## 4. Personalization and feedback

- On provisioning, create empty Tone and Design profiles.
- `StyleLearnerAgent` runs after sessions with explicit preference signals ("shorter", "use bullets", edits to drafts) and proposes a profile diff; small diffs auto-apply, larger ones show in Settings for approval. Each change bumps `version`.
- Feedback on any brief/draft/label ("Give feedback") creates `assistant_feedback`; `FeedbackApplier` turns it into a standing instruction on the matching config (briefing instructions, label definition, drafting instructions). Schedule, sources and delivery never change unless the user asks explicitly.
- Chat can do the same through the `update_*` tools.

---

## 5. Briefings engine (Daily + Meeting Prep)

### Run
1. `assistant:run-due-briefings` (every minute) finds due daily configs in the user's timezone and creates `assistant_briefing_runs` with `run_key = daily:{Y-m-d}` (unique → no duplicates).
2. `RunBriefingJob` (`ShouldBeUnique` by run key):
   - Resolve connectors: `all` = every credential the user has at run time; `selected` = pinned list (≤50).
   - Fan out `CollectSourceJob` per credential in a `Bus::batch` with `allowFailures()`. Each `SourceCollector` reads since its cursor (first run: 16h back), read-only, returns normalized items `{source, id, title, snippet, url, at, people}` and records ok/error in `source_results`.
   - `BriefingWriterAgent` with read-only tools (so it can look deeper) writes:
     - `summary`: 2 sentences, ≤40 words (meeting: 2–3 sentences, ≤60 words)
     - `document`: Daily = `## Catch-up` (with citation chips); Meeting = `## Attendees`, `## Prior context`, `## Open questions`, `## Talking points`; empty sections omitted; no title/date/preamble
     - `situations[]`: one thing per card, only concrete work the user owns with an outcome the assistant could deliver; each with steps
   - Advance cursors **only** for successful sources.
   - `DeliverBriefingJob`: in-app always; email/Slack DM/SMS per `delivery`; writes `delivered_at` inside a transaction guarded by `whereNull('delivered_at')` so retries can't double-send.
3. Pause/resume toggles `paused_at`; history is kept.

### Situations
- Card: title, summary, optional next step, citations, ordered steps (todo/done/skipped).
- "Send to Assistant" → creates a session (`origin=task`) seeded with the situation and its steps; the loop works through them and ticks steps via an internal tool.
- Dismiss → `dismissed`.

### Meeting Prep
- `assistant:sync-calendars` every 10 minutes → `SyncCalendarJob` upserts the next 7 days into `assistant_meetings` (Google Calendar now, Outlook in Track E).
- External = at least one guest other than the user with a different email domain. Scope default `external_only`.
- When `starts_at - minutes_before <= now` and `auto` is on → `PrepareMeetingJob` creates a run with `run_key = meeting:{event}:{start}`.
- "Prepare now" works regardless of `auto`.
- Slack delivery includes a "Give feedback" button and source links.

---

## 6. Smart Inbox

### Enable
1. User picks a Gmail (Outlook later) credential. Check scopes: read + modify (+ labels on Gmail) required; draft and archive optional → `capabilities`.
2. Seed builtins: Needs reply, Time sensitive, Waiting on you, FYI, Low priority (definitions from the Gumball doc). `LabelSync` adopts any existing provider label with the same name.
3. Gmail: `users.watch` to a Pub/Sub topic → `POST webhooks/gmail/push`. Store `historyId`; `assistant:renew-inbox-watches` renews daily (watch expires in 7 days). Polling fallback every 2 minutes if Pub/Sub is not configured.
4. One mailbox per user; connecting another replaces it (clears records, leaves provider labels in place).

### Per message (`ClassifyEmailJob`, unique by provider message id)
1. Skip if received before enable, or `skip_existing_labels` and the message already has a user label.
2. `InboxClassifierAgent` against enabled label definitions → at most 5 labels, drop low confidence.
3. Apply labels; archive only if **every** applied label is in `move_out` and `can_archive`.
4. If "Needs reply" (or the classifier says a reply is warranted) and `draft_mode = confident` → `DraftReplyJob`.

### Drafting rules (`ReplyDrafter`)
- Skip: no-reply/newsletter/system senders, invites with no message, nothing substantive, ask not aimed at the owner.
- Known senders only (default): the user has emailed this sender before, or shares the work domain.
- Context: the thread, up to 3 past replies to this sender, drafting instructions (outrank Tone), Tone profile, optional read-only grounding via selected connectors and Brain.
- Reply-all: keep original To/Cc, remove own address and Bcc.
- Draft only when confident; otherwise store `suggestion` (shown in the app, "Accept into chat").
- Never overwrite a draft the user edited (compare `draft_hash` with the provider's current draft); an untouched previous draft may be replaced.
- Never sends.

### Label management
- Max 25 labels; builtins can be edited/disabled, not deleted.
- Changes affect future mail only. Rename → rename Gmail label; Outlook keeps old category on old mail.
- Pro plan and above (`PlanLimitGate`).

---

## 7. Channels

### Slack DM
- Add Events API (`message.im`) to the existing Slack app; route `POST webhooks/slack/events` with signing-secret verification and the URL-verification handshake.
- Map Slack user → email (`users.info`) → workspace member → their Assistant; store in `assistant_channel_links`. No match → reply with a sign-up link.
- Top-level DM starts a session (`external_thread_ref = channel:ts`); thread replies continue it.
- Commands: `!stop` (cancel turn), `!link` (link to the session in the web app).
- Approvals as Block Kit buttons → `webhooks/slack/assistant-actions`.
- Old installs without DM scopes → reply asking an admin to reconnect.

### Email
- Inbound provider (Postmark or Mailgun inbound) → `POST webhooks/email/inbound`.
- Address: one fixed address on your domain; sender matched by verified user email. Optionally `<token>@in.<domain>` per user.
- Check DKIM/SPF result from the provider; reject unauthenticated senders.
- Thread by `Message-ID`/`In-Reply-To` → `external_thread_ref`; reply in the same thread via Laravel Mail.

### SMS / iMessage (Enterprise)
- Provider: Twilio (SMS) and/or an iMessage/RCS provider.
- Phone verification: 6-digit code, 10-minute expiry, resend after 60s, 5 attempts per code, 5 codes per hour; one account per number.
- Threading: within 20 minutes → continue; after 7 days → new; in between → `ThreadRouterAgent`.
- Replies: plain text, 1,500-character chunks, max 6, then "Open in app" link. Images as MMS, other files as links.
- Rate limit: 20/min and 200/hour per sender.

All channels call the same `HandleChannelMessageJob` → `AssistantLoop`; only input parsing and output formatting differ (`ChannelReplyFormatter`).

---

## 8. Triggers

- Types: schedule (cron, min 1 minute), once (auto-delete after run), webhook (`POST webhooks/assistant/hooks/{token}`, 100 req/min, returns 200 immediately), app events (Gmail new mail, Slack message, Drive change; polling at ~60s where push is unavailable).
- Created in Settings or by the assistant in chat (`create_trigger` → `pending_approval` until the user approves). Natural-language schedules via `ScheduleParserAgent`.
- Run = a new session with the rendered prompt template; optional model override.
- Skip if the previous run of the same trigger is still running. Auto-disable after 3 consecutive failures and notify the owner (in-app, email, Slack DM).

---

## 9. Computer, artifacts and Brain

### Sandbox (Track B)
- Managed provider behind `SandboxDriver` (E2B first). One sandbox per session, resumed by id, killed after idle timeout.
- Persistent volume: `.workspace/personal` (per user) mounted into every sandbox.
- Secrets from the existing vault injected as env vars; values never returned to the model.
- A small Python package (`assistant_sdk`) preinstalled; calls `POST /api/internal/assistant/sandbox/tools/{tool}` with a short-lived signed token so code can use the user's connectors through the same `ToolGate`.
- Limits: 30-minute command timeout, output truncation, credit charge per sandbox minute.

### Artifacts
- `export_artifact` stores files; same name in the same session → new version.
- Preview cards with thumbnails; viewer page; access levels restricted / workspace / password (6–256 chars, 24h unlock, throttled) / public.
- Hosting: `{alias}.<artifacts-domain>` served by a separate route group with strict CSP.
- Interactive artifacts (last): HTML in a sandboxed iframe + a Python data script run in the sandbox on open, using the viewer's credentials after a consent overlay; 5-minute script timeout.

### Brain (Track C)
- Sources: Drive, Gmail (by label), Slack public channels, GitHub, uploads. Personal scope only for the Assistant.
- `assistant:brain-sync` hourly → `SyncBrainSourceJob` per source; incremental via cursor; removes deleted documents.
- Hybrid search: vector (pgvector or Laravel AI vector store) + full-text, merged with reciprocal rank fusion, then reranked.
- Drive ACLs checked at query time so the user only sees what the provider lets them see.
- Artifacts indexed (latest version only).

---

## 10. HTTP API (`routes/api/internal/assistant.php`, `auth:api`, `workspace.context`, policy = owner)

```
GET    /assistant                                   provision-if-missing + home payload
PATCH  /assistant                                   display name, model, instructions, settings
GET    /assistant/sessions                          ?origin=&q=
POST   /assistant/sessions                          {incognito?}
GET    /assistant/sessions/{s}
PATCH  /assistant/sessions/{s}                      title, archive
DELETE /assistant/sessions/{s}
GET    /assistant/sessions/{s}/messages             cursor paginated
POST   /assistant/sessions/{s}/messages             {content, attachments[], mode: send|redirect|queue}
POST   /assistant/sessions/{s}/turns/{t}/cancel
GET    /assistant/sessions/{s}/context              context meter
GET    /assistant/sessions/{s}/actions
POST   /assistant/sessions/{s}/actions/decisions    [{action_id, decision, remember?}]
POST   /assistant/transcribe

GET|PUT        /assistant/style/{tone|design}
GET|POST       /assistant/memories ; DELETE /assistant/memories/{m}
GET|PUT        /assistant/tool-rules
POST           /assistant/feedback

GET|PUT        /assistant/briefings/{daily|meeting_prep}
POST           /assistant/briefings/{type}/pause | resume | run-now
GET            /assistant/briefings/{type}/runs ; GET /assistant/briefing-runs/{r}
GET            /assistant/situations?status= ; PATCH /assistant/situations/{x}
POST           /assistant/situations/{x}/send ; PATCH /assistant/situations/{x}/steps/{st}
GET            /assistant/meetings ; POST /assistant/meetings/{m}/prepare

GET|PUT|DELETE /assistant/inbox                     enable/configure/disable
GET|POST       /assistant/inbox/labels ; PATCH|DELETE /assistant/inbox/labels/{l} ; PUT /assistant/inbox/labels/order
GET            /assistant/inbox/messages
POST           /assistant/inbox/messages/{m}/accept-suggestion

GET|POST       /assistant/triggers ; PATCH|DELETE /assistant/triggers/{t} ; POST /assistant/triggers/{t}/approve

GET            /assistant/channels                  slack/email/sms status
POST           /assistant/channels/sms/verify-start | verify-confirm ; DELETE /assistant/channels/{link}

GET            /assistant/artifacts ; GET /assistant/artifacts/{a} ; PATCH (access, password, alias)
GET            /assistant/artifacts/{a}/versions/{v}/download

GET|POST       /assistant/brain/sources ; DELETE /assistant/brain/sources/{src} ; POST /assistant/brain/sources/{src}/sync
POST           /assistant/brain/search
```

### Realtime (Reverb)
- Channel `private-assistant.{userId}` → `BriefingCompleted`, `SituationCreated`, `InboxMessageClassified`, `ActionRequested`.
- Channel `private-assistant.{userId}.sessions.{sessionId}` → `TurnStarted`, `TurnDelta`, `ToolCalled`, `ActionRequested`, `TurnFinished`.
- Authorize in `routes/channels.php`: `$user->id === $userId` and the session belongs to that user's assistant.

---

## 11. Frontend (`../agent1o1/src`) — fully separate from Agents

```
Routes/pages/assistant.pages.ts       new page tree; Routes/navigation.ts → "Assistant" as the first nav item
api/modules/assistant/
  assistant.endpoints.ts  assistant.keys.ts  assistant.realtime.ts
  sessions.{service,hooks}.ts   style.{service,hooks}.ts   memories.{service,hooks}.ts
  briefings.{service,hooks}.ts  situations.{service,hooks}.ts  meetings.{service,hooks}.ts
  inbox.{service,hooks}.ts      triggers.{service,hooks}.ts    channels.{service,hooks}.ts
  artifacts.{service,hooks}.ts  brain.{service,hooks}.ts
types/assistant.type.ts
components/assistant-chat/
  Composer.tsx            text, attachments, mic, send / redirect / queue while running, stop
  MessageList.tsx         virtualized; recap messages collapsed
  MessageBubble.tsx       markdown, citations chips, tool call rows (collapsible)
  ApprovalCard.tsx        approve/reject, "always allow" checkbox
  ArtifactCard.tsx        thumbnail, version badge, open viewer
  ContextMeter.tsx        token breakdown popover
  TurnStatus.tsx          running / awaiting approval / step x of y
pages/coreapp/Assistant/
  Home.page.tsx           chat hero + tabs + right rail
  Session.page.tsx        full chat for one session
  _partial/
    RightRail.partial.tsx         Email, Phone, Inbox, Calendar, Slack, Connectors, Triggers, Personalization
    SessionSidebar.partial.tsx    history, search, incognito toggle
    SituationsTab.partial.tsx     SituationCard (steps checklist, Send to Assistant, dismiss)
    DailyTab.partial.tsx          run list, run detail (summary, document, failed-source badges), feedback
    InboxTab.partial.tsx          classified mail, suggestions, accept into chat
    MeetingsTab.partial.tsx       upcoming meetings, Prepare now, brief viewer
    ArtifactsTab.partial.tsx
  Settings/
    DailySettings.partial.tsx     time, days, timezone, connectors, instructions, model, delivery
    MeetingPrepSettings.partial.tsx
    InboxSettings.partial.tsx     account, model, draft mode, drafting instructions, known senders, grounding
    ManageLabels.partial.tsx      drag between Keep / Move out (@hello-pangea/dnd), colour, definition
    Personalization.partial.tsx   Tone + Design editors, version history
    ToolRules.partial.tsx
    Triggers.partial.tsx          list, approve pending, pause, delete, NL schedule input
    Channels.partial.tsx          Slack DM status, inbound email address, phone verification
    BrainSources.partial.tsx
  ArtifactViewer.page.tsx         preview, versions, share dialog (access, password, alias)
```

- Data: TanStack Query for everything; Reverb events invalidate or patch the relevant query cache (`assistant.keys.ts`).
- Streaming: subscribe to the session channel on mount; append `TurnDelta` to an in-memory draft message; replace with the persisted message on `TurnFinished`.
- Nothing under `pages/coreapp/Agents` or `api/modules/agents` is imported. Only generic `components/ui/*` is shared.

---

## 12. Billing, limits and permissions

| Feature | Plan | Permission |
|---|---|---|
| Assistant chat | all | `assistant.access` |
| Daily report | all (≥1 connector) | `assistant.daily` |
| Meeting Prep | all (calendar connected) | `assistant.meeting_prep` |
| Smart Inbox | Pro+ | `assistant.inbox` |
| Brain | Pro+ | `assistant.access` |
| Sandbox | Pro+ | `assistant.access` |
| SMS/iMessage | Enterprise | `assistant.sms` |

- `CreditGate` checked before each turn/briefing/classification; out of credits → background features skip and record why, chat shows an upgrade prompt.
- Limits in `config/assistant.php`: step budget 100 (max 200), 10 concurrent sub-tasks, 50 selected connectors, 4000-char instructions, 25 labels, 5 labels per message.

---

## 13. Testing (Pest)

- **Runtime**: turn lock (second message queues), redirect input appears before the next step, cancel stops between steps, step budget ends the turn cleanly, approval pause/resume round trip, destructive can never be auto-allowed, compaction keeps the protected tail and the recap replaces older messages in context. Use Laravel AI fakes for model responses.
- **Briefings**: write tools absent from catalog, failed source keeps its cursor, first run looks back 16h, retry delivers once, empty sections omitted, situation send creates a task session.
- **Inbox**: keep-wins archive rule, 5-label cap, low-confidence dropped, known-senders filter, skip rules, reply-all recipients, edited draft never overwritten, label rename/adopt, disconnect clears records.
- **Channels**: Slack signature + URL verification, user matching, thread → session mapping, `!stop`; email DKIM rejection and threading; SMS verification limits, chunking, rate limit, 20-minute/7-day routing.
- **Triggers**: pending approval, overlap skip, auto-disable after 3 failures.
- **Policy**: another workspace member gets 403 on every route and on broadcast channel auth.
- `Http::fake` for every provider API; `Queue::fake`/`Bus::fake` for fan-out; `Event::fake` for broadcasts.

---

## 14. Delivery order

| # | Milestone | Backend | Frontend | Est. |
|---|---|---|---|---|
| 1 | Foundations | migrations core + tools tables, models, enums, policy, provisioner, config | page tree, nav item, api module skeleton | 1 wk |
| 2 | Runtime | `AssistantLoop`, queued turns, Reverb events, tool gate, approvals, stop, step budget, built-in tools (memory, skills, web) | chat components, Home + Session pages | 3 wks |
| 3 | Connector tools | `ConnectorToolFactory` over existing clients with effects | connector rows in right rail | 1 wk |
| 4 | Context | compaction, context meter, queue/redirect, incognito, voice | meter, composer modes, mic | 1 wk |
| 5 | Personalization | style profiles, learner, feedback applier | Personalization settings | 1 wk |
| 6 | Daily + Situations | briefing engine, collectors, cursors, delivery router | Situations + Daily tabs, settings | 2–3 wks |
| 7 | Meeting Prep | calendar sync, prep jobs | Meetings tab, settings | 1–2 wks |
| 8 | Smart Inbox (Gmail) | provider, ingest, classifier, drafter, label sync | Inbox tab, labels, settings | 2–3 wks |
| 9 | Triggers | assistant triggers + chat tools | Triggers settings | 1 wk |
| 10 | Channels | Slack DM, inbound email, SMS | Channels settings | 2–3 wks |
| 11 | Sandbox + artifacts | sandbox driver, SDK bridge, artifact versions, hosting | artifact cards, viewer, share | 3 wks |
| 12 | Brain | syncers, hybrid search, ACLs | Brain sources settings | 2–3 wks |
| 13 | Outlook + more connectors | Graph mail/calendar provider, more collectors | — | 2–3 wks |

Milestones 1–6 (~10 weeks) give a usable product: a personal assistant with chat, connectors, personalization, and a Daily report with Situations.

---

## 15. Risks and decisions to make

- **Gmail restricted scopes** (`gmail.modify`) require Google's CASA assessment before public launch; plan this early.
- **Pub/Sub / Graph subscriptions** add infrastructure; polling is the fallback.
- **Slack DM scopes** (`im:history`, `im:write`, `users:read.email`) mean existing installs must reconnect.
- **Sandbox provider** choice and cost per minute; must be metered.
- **Vector store**: pgvector requires Postgres; check the production database before Track C.
- **Inbox classification cost**: default to a small model; skip obvious newsletters before calling the model.
- **Duplicated runtime**: fixes to approvals or streaming in Agents will not reach the Assistant automatically; keep tests strong on both sides.
- Open: product/brand name; whether teams' skills are searchable by default; SMS provider.
