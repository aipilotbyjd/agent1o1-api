# PostHog analytics

Product analytics on PostHog Cloud, covering both repos: `agent1o1-api` (server events) and `agent1o1` (browser: pageviews, identity, session replay). Both are off until a project token is set.

## Setup

1. In PostHog Cloud, open Project settings and copy the **project token** (`phc_...`). Note the region: US is `https://us.i.posthog.com`, EU is `https://eu.i.posthog.com`.
2. Use the **same token** on both sides so browser and server events land on one person.

| Where | Variable | Value |
| --- | --- | --- |
| API `.env` | `POSTHOG_PROJECT_TOKEN` | the `phc_...` token |
| API `.env` | `POSTHOG_HOST` | region host (default US) |
| API `.env` | `POSTHOG_ENABLED` | `true` (default). `false` switches it off. |
| API `.env` | `POSTHOG_TIMEOUT` | seconds per request (default 5) |
| Frontend env (Netlify) | `VITE_POSTHOG_PROJECT_TOKEN` | the same token |
| Frontend env (Netlify) | `VITE_POSTHOG_HOST` | the same host |

Do not put the token in the frontend's committed `.env`. Set it in the host's environment settings. The frontend variables are baked in at build time, so redeploy after changing them.

Server events go out on the `system-notification` queue, which Horizon already runs. Keep a worker up.

## Backend

- `config/posthog.php`: token, host, enabled flag, timeout.
- `App\Services\Analytics\ProductAnalytics::capture($userId, $event, $properties, $workspaceId)`: the one entry point. It queues the event and never throws, so analytics cannot break signup, billing or a run.
- `App\Jobs\Analytics\CapturePostHogEvent`: sends one event, 3 tries with backoff, then drops it.

### Events

| Event | Fired from | Properties |
| --- | --- | --- |
| `user_signed_up` | `AuthService::register`, social sign-up | `method` (`email` or provider) |
| `email_verified` | `Verified` listener | none |
| `agent_created` | `AgentObserver::created` | `agent_id` |
| `workflow_created` | `WorkflowObserver::created` (internal workflows skipped) | `workflow_id` |
| `run_completed` / `run_failed` | `CaptureRunFinished` listener | `run_id`, `runnable_type`, `trigger_type`, `is_child_run`, `duration_ms` |
| `subscription_started` | Stripe `customer.subscription.created` | `stripe_status`, `is_trial` |
| `subscription_canceled` | Stripe `customer.subscription.deleted` | `stripe_status`, `is_trial` |

Events with a workspace are grouped under the `workspace` group. A run with no `triggered_by` user is sent as an anonymous event (`$process_person_profile = false`) under `workspace:{id}`. Subscription events are attributed to the workspace owner.

### Adding an event

```php
app(ProductAnalytics::class)->capture(
    (string) $user->getKey(),
    'connector_connected',
    ['connector' => 'gmail'],
    (string) $workspace->getKey(),
);
```

### Privacy rules

Send ids, counts, durations and enums only. Never send emails, prompts, agent or assistant messages, file names, or anything from a credential or secret. The browser identifies people with their email; the server does not need to.

### Testing

`tests/Feature/Analytics/ProductAnalyticsTest.php` uses `Bus::fake()` and sets `posthog.project_token`. With no token configured (the default in tests) nothing is dispatched.

## Frontend

- `src/utils/posthog.util.ts`: `initPostHog()` runs once in `src/main.tsx` before React renders, only if a token is set. Pageviews follow route changes. Anonymous visitors get no person profile until they sign in.
- `src/context/analytics/PostHogSync.tsx` (mounted in `AuthProvider`): identifies the user by their API id, sets the `workspace` group from the active workspace, and calls `reset()` on sign-out.
- Session replay masks all inputs. Add the class `ph-no-capture` to any element that must not be recorded, such as agent chat content or secrets.
- If you add a Content Security Policy, allow `https://*.posthog.com` in `script-src` and `connect-src`, and `blob: data:` in `worker-src`.

## Verify it works

1. Set the tokens, deploy both, and keep a queue worker running.
2. Sign up, create an agent and run it.
3. In PostHog, open Activity and look for `$pageview`, `user_signed_up`, `agent_created` and `run_completed`. The same person should show browser and server events.

## Useful PostHog setup

- Funnel: `user_signed_up` → `email_verified` → `agent_created` → `run_completed`.
- Billing: `subscription_started` → `subscription_canceled`.
- Enable group analytics for `workspace` to see usage per workspace.
- Feature flags and experiments work through both SDKs.
- Error tracking stays on Sentry/GlitchTip.
