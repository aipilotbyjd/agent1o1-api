# Assistant Branding Plan (rename-proof "Orb")

## Context

The personal assistant (see `docs/ASSISTANT_PLAN.md`) ships as **Orb**, but the brand is expected to change often. Goal: a rename is a config change, never a code change — no migration, no refactor, no frontend rebuild, no broken email addresses.

**Rule:** the string "Orb" appears in exactly one place — the brand config. Everything else (classes, tables, routes, events, prompts, emails, UI text) is either neutral (`Assistant`) or reads the brand at runtime.

Scope: **Level A** (config-based, rename = edit `.env` + `php artisan config:cache`). Built behind a `BrandRepository` interface so **Level B** (database-stored, renamed from an admin screen, per-workspace white-label) is a drop-in later.

---

## 1. Naming rules for code

| Layer | Uses | Never |
|---|---|---|
| PHP namespaces / classes | `App\…\Assistant\*`, `AssistantLoop` | `OrbLoop` |
| Tables / columns | `assistant_sessions`, `assistant_id` | `orb_sessions` |
| Routes | `/assistant/*`, route names `assistant.*` | `/orb/*` |
| Events / broadcast channels | `assistant.{userId}` | `orb.{userId}` |
| Config keys / env vars | `assistant.brand.*`, `ASSISTANT_*` | `ORB_*` |
| FE folders / modules | `pages/coreapp/Assistant`, `api/modules/assistant` | `pages/Orb` |
| FE routes (URL) | `/assistant` | `/orb` |
| Queue names, cache keys, log channels | `assistant` | `orb` |
| Provider-side artifacts (Gmail labels, Outlook categories, Slack block ids) | neutral names | brand-prefixed |

---

## 2. Backend

### 2.1 Config — `config/assistant.php`
```php
'brand' => [
    'name'        => env('ASSISTANT_NAME', 'Orb'),
    'tagline'     => env('ASSISTANT_TAGLINE', 'Your personal AI agent'),
    'description' => env('ASSISTANT_DESCRIPTION', 'Reads your apps, preps your day and handles your inbox.'),
    'emoji'       => env('ASSISTANT_EMOJI', '🔮'),
    'color'       => env('ASSISTANT_COLOR', '#7C3AED'),
    'icon_url'    => env('ASSISTANT_ICON_URL', '/brand/assistant/icon.svg'),
    'avatar_url'  => env('ASSISTANT_AVATAR_URL', '/brand/assistant/avatar.png'),

    // Feature names — `:name` is replaced at runtime.
    'features' => [
        'daily'        => env('ASSISTANT_FEATURE_DAILY', ':name Daily'),
        'inbox'        => env('ASSISTANT_FEATURE_INBOX', ':name Inbox'),
        'meeting_prep' => env('ASSISTANT_FEATURE_PREP', ':name Prep'),
        'situations'   => env('ASSISTANT_FEATURE_SITUATIONS', 'Situations'),
    ],

    // Channels
    'email_local_part' => env('ASSISTANT_EMAIL', 'orb'),               // orb@<inbound domain>
    'email_aliases'    => array_filter(explode(',', env('ASSISTANT_EMAIL_ALIASES', ''))), // old names keep working
    'inbound_domain'   => env('ASSISTANT_INBOUND_DOMAIN', 'agent1o1.com'),
    'sms_signature'    => env('ASSISTANT_SMS_SIGNATURE', '— :name'),
],
```
`.env.example` gets every `ASSISTANT_*` key with the Orb defaults.

### 2.2 Brand value object and repository
```
app/Services/Assistant/Branding/
  Brand.php                     final readonly value object
  BrandRepository.php           interface: current(?Workspace $workspace = null): Brand
  ConfigBrandRepository.php     Level A: builds Brand from config('assistant.brand')
```
- `Brand` holds every field above and exposes helpers:
  - `name()`, `feature(string $key)` (resolves `:name`), `email()` (`local@domain`), `acceptsEmailTo(string $address)` (current + aliases), `toArray()` (for the API).
- Bound as a singleton in `AppServiceProvider`: `BrandRepository::class => ConfigBrandRepository::class`.
- **Every** consumer type-hints `BrandRepository`, never reads config directly. Level B swaps the binding only.
- The `?Workspace` parameter is unused in Level A; it exists so per-workspace white-label needs no signature change.

### 2.3 Where the brand is used (each reads `Brand`)

| Area | File(s) | How |
|---|---|---|
| AI system prompt | `Ai/Assistant/AssistantAgent`, `ContextBuilder` | instructions template contains `{brand_name}`; replaced when the prompt is built. The model introduces itself by the current name |
| Briefing / inbox / meeting agents | `BriefingWriterAgent`, `ReplyDrafterAgent`, … | same placeholder; drafts written as the user, never signed with the brand |
| Notifications | `app/Notifications/Assistant/*` | subjects/lines use `__('assistant.daily.subject', ['feature' => $brand->feature('daily')])` |
| Mail | `Mailables` for briefings and email-channel replies | from name `= $brand->name()`, from address `= $brand->email()` |
| Inbound email | `InboundEmailHandler` | accepts any address where `$brand->acceptsEmailTo()` is true |
| Slack DM | `ChannelReplyFormatter` | header/context blocks use the brand name; bot display name comes from the Slack app (see §5) |
| SMS | `SmsHandler` | signature from `sms_signature` |
| Backend translations | `lang/en/assistant.php` | all strings take `:name` / `:feature` placeholders, no literal brand |
| API | `GET /assistant`, plus public `GET /api/internal/v1/app-config` | returns `brand: Brand::toArray()` |

### 2.4 API shape
```json
"brand": {
  "name": "Orb",
  "tagline": "Your personal AI agent",
  "description": "…",
  "emoji": "🔮",
  "color": "#7C3AED",
  "icon_url": "https://…/brand/assistant/icon.svg",
  "avatar_url": "https://…/brand/assistant/avatar.png",
  "features": { "daily": "Orb Daily", "inbox": "Orb Inbox", "meeting_prep": "Orb Prep", "situations": "Situations" },
  "email": "orb@agent1o1.com"
}
```
`app-config` is cached (`Cache::rememberForever('assistant.brand')`, busted by `assistant:brand-refresh`) and served with a short `Cache-Control` so a rename shows up within minutes without a frontend deploy.

### 2.5 Assets
- `public/brand/assistant/{icon.svg, avatar.png, og.png}` — neutral filenames; swapping the files (or pointing `ASSISTANT_ICON_URL` to a CDN) rebrands images.

### 2.6 Provider-side names (Smart Inbox)
- Built-in labels are created in Gmail/Outlook **without** a brand prefix (`Needs reply`, not `Orb/Needs reply`).
- If a prefix is ever wanted, store it as `assistant_inbox_configs.label_prefix` captured at enable time, and `LabelSync::renamePrefix()` renames existing Gmail labels when the brand changes (Outlook categories cannot be renamed — new mail only, as in the main plan).

### 2.7 Commands
- `assistant:brand-show` — prints the resolved brand (sanity check after editing `.env`).
- `assistant:brand-refresh` — clears the brand cache and broadcasts `BrandChanged` on a public channel so open tabs refetch.

---

## 3. Frontend (`../agent1o1/src`)

### 3.1 Data
```
api/modules/app-config/
  app-config.endpoints.ts      GET /app-config
  app-config.service.ts
  app-config.hooks.ts          useAppConfig()
types/brand.type.ts            TBrand
config/brand.default.ts        fallback brand (used before the API answers and in tests)
```
- `useAssistantBrand(): TBrand` — reads from `useAppConfig()`, falls back to `brand.default.ts`; `staleTime` 5 minutes.
- Listen for `BrandChanged` (Echo, public channel) → invalidate the app-config query.

### 3.2 Context + i18n
```
context/BrandContext.tsx       <BrandProvider> at the root (Providers/), exposes brand
```
- On brand load, register i18next default interpolation variables so every string can use them without passing props:
  ```ts
  i18n.options.interpolation.defaultVariables = {
    assistantName: brand.name,
    assistantDaily: brand.features.daily,
    assistantInbox: brand.features.inbox,
    assistantPrep: brand.features.meeting_prep,
  };
  ```
- Locale files (`locales/{en,es,ar}/translation.json`) hold an `assistant` namespace with placeholders only:
  ```json
  "assistant": {
    "nav": "{{assistantName}}",
    "composerPlaceholder": "Ask {{assistantName}} anything…",
    "greeting": "Hi {{userName}}, I'm {{assistantName}}.",
    "dailyTab": "{{assistantDaily}}",
    "inboxTab": "{{assistantInbox}}",
    "prepTab": "{{assistantPrep}}"
  }
  ```

### 3.3 Visual brand
- `components/assistant/BrandMark.tsx` — icon/avatar/emoji from brand, sizes `sm|md|lg`.
- CSS variable `--assistant-color` set on `<BrandProvider>` from `brand.color`; Tailwind v4 theme token `--color-assistant` maps to it, so components use `bg-assistant` / `text-assistant`.
- Document title and favicon on Assistant pages come from the brand.
- Sidebar entry in `Routes/navigation.ts` uses the i18n key, not a literal.

### 3.4 What stays neutral
- Route path `/assistant`, page folder `pages/coreapp/Assistant`, query keys `['assistant', …]`, Echo channel names.

---

## 4. Guard rails (stop "Orb" leaking into code)

### Backend — Pest arch/feature test
`tests/Feature/Assistant/BrandingGuardTest.php`:
- Reads the current brand name from config and scans `app/`, `routes/`, `resources/views/`, `lang/`, `database/` for it (case-insensitive, whole word). Allowed only in `config/assistant.php` and `.env.example`. Fails with the file and line.
- Asserts `AssistantAgent` instructions render with the configured name: set `config(['assistant.brand.name' => 'Testy'])` → prompt contains "Testy", not "Orb".
- Asserts notification subject, mail from-name and `GET /app-config` follow a config override.
- Asserts inbound email to an alias address is accepted and to an unknown address is rejected.

### Frontend
- ESLint `no-restricted-syntax` rule in `eslint.config.mjs` banning string literals and JSX text matching `/\bOrb\b/i` outside `config/brand.default.ts`.
- A small test rendering the Assistant home with a mocked brand `{ name: 'Testy' }` and asserting "Ask Testy anything…".

---

## 5. Rename checklist (things code can't change)

| Item | Action on rename |
|---|---|
| `.env` | update `ASSISTANT_*`, add old email to `ASSISTANT_EMAIL_ALIASES`, run `php artisan config:cache && php artisan assistant:brand-refresh` |
| Brand images | replace files in `public/brand/assistant/` or update URLs |
| Inbound email | add the new address/route at the inbound provider (Postmark/Mailgun); keep the old one forwarding |
| Slack app | change the bot display name and icon in the Slack app settings |
| SMS sender | update sender name/number profile at the SMS provider if branded |
| Marketing site / docs | outside this repo |

Target: a rename takes under 10 minutes and zero code changes.

---

## 6. Level B upgrade path (later, optional)

- New table `brand_settings` (`scope` = global | workspace, `workspace_id` nullable, `values` json, timestamps).
- `DatabaseBrandRepository` merges: config defaults → global row → workspace row; cached per scope; `BrandChanged` on save.
- Admin screen (operator) for the global brand; Settings screen for workspace white-label (plan-gated).
- Swap the container binding. No consumer changes because everything already depends on `BrandRepository`.

---

## 7. Tasks and order

| # | Task | Where | Est. |
|---|---|---|---|
| 1 | `config/assistant.php` brand block + `.env.example` keys | backend | 0.5 d |
| 2 | `Brand`, `BrandRepository`, `ConfigBrandRepository`, binding | backend | 0.5 d |
| 3 | `GET /app-config` + brand on `GET /assistant`, caching, `BrandChanged` event, commands | backend | 0.5 d |
| 4 | `lang/en/assistant.php` with placeholders; prompt templates with `{brand_name}` | backend | 0.5 d |
| 5 | Guard test + override tests | backend | 0.5 d |
| 6 | `app-config` module, `useAssistantBrand`, `BrandProvider`, i18n default variables | frontend | 1 d |
| 7 | Locale keys (en/es/ar), `BrandMark`, `--assistant-color` token, nav entry | frontend | 0.5 d |
| 8 | ESLint guard rule + render test | frontend | 0.5 d |

Total ≈ 4–5 days. Do tasks 1–3 as part of Milestone 1 of `docs/ASSISTANT_PLAN.md`, so every later feature is built on the brand layer from the start.
