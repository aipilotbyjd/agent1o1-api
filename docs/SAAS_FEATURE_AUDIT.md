# SaaS Feature Audit

An audit of the platform-level SaaS concerns — billing, account lifecycle,
tenancy, compliance, operations — as distinct from the product itself
(workflows, agents, nodes, runs), which is tracked in `PLAN.md` and the
per-domain plans alongside it.

Everything below was verified against the code at the time of writing;
each gap cites the file that proves it. Product-feature gaps are out of
scope here.

---

## 1. What already exists

| Area | Status |
| --- | --- |
| Auth | Register, login, refresh, password reset, email verification endpoints, 2FA + recovery codes, Google/GitHub social login, session listing and revocation (`routes/api/internal/auth.php`) |
| Tenancy | Workspaces, members, invitations, ownership, workspace switching (`routes/api/internal/workspaces.php`) |
| Authorization | ~30-case `Permission` enum with a role→permission map, enforced through `Controller::requirePermission()` (`app/Enums/Workspaces/Permission.php`) |
| API surface | Two surfaces — internal (Passport) and public (workspace-scoped `ApiKey`), the latter versioned under `/api/public/v1` |
| Billing | Cashier with four intervals (monthly/quarterly/yearly/lifetime), trials, credit packs, plan grants for lifetime + comped access, idempotent Stripe webhooks, refund and dispute revocation |
| Metering | Append-only `CreditTransaction` ledger, `UsagePeriod` rollups, `CreditGate` pre-flight refusal, overdraft-tolerant deduction |
| Notifications | In-app, email, and workspace-webhook channels with per-event preferences and an admin alert channel |
| Onboarding | Multi-step state machine with dismissal (`routes/api/internal/onboarding.php`) |
| Ops | Horizon (per-queue supervisors), Pulse, Reverb, `/up` health check, scheduled maintenance commands |
| Tests | 123 test files across feature and unit suites |

Entitlement resolution deserves a specific call-out as already correct:
`Workspace::activeSubscription()` (`app/Models/Workspaces/Workspace.php:216`)
gates on Cashier's `valid()`, so `past_due` and canceled subscriptions
correctly stop entitling, and `currentPlan()` resolves grant-vs-subscription
by picking the more generous of the two.

---

## 2. Gaps

### P0 — revenue or correctness impact

#### 2.1 Plan limits are never enforced — RESOLVED

`PlansDataset` defines per-plan caps — `['workflows' => 3, 'agents' => 1,
'members' => 2]` on Free (`database/seeders/PlansDataset.php:44`), 25/10/5 on
Starter (`:63`), unlimited on Pro (`:82`). The column is cast to an array on
the model (`app/Models/Billing/Plan.php:25`) and echoed back to the client in
`PlanResource` (`app/Http/Resources/Api/Internal/V1/Billing/PlanResource.php:30`).

That is the entire lifecycle of the value. No code path reads it to make a
decision. `WorkflowController::store()` creates unconditionally
(`app/Http/Controllers/Api/Internal/V1/Workflows/WorkflowController.php:28`),
as do `AgentController::store()` (`:28`) and
`WorkspaceInvitationController::store()`
(`app/Http/Controllers/Api/Internal/V1/Workspaces/WorkspaceInvitationController.php:33`).

**Effect:** the paid tiers' non-credit differentiators are advertised on the
pricing screen and unenforced in the API. See §3 for the full treatment.

**Resolved** by `Services\Billing\PlanLimitGate`, called from every creation
path on both API surfaces. `GET /billing` now also reports used-vs-max per
limit. Caps remain plan-level data (`plans.limits`); there are no
per-workspace overrides.

#### 2.2 No invoice or billing-history endpoints — RESOLVED

`routes/api/internal/billing.php` exposes overview, plans, subscription
(show/checkout/cancel/resume), credit packs, and the credit ledger. There is
no invoice route. Cashier already provides `invoices()`,
`invoicesIncludingPending()`, `findInvoice()`, and `upcomingInvoice()` against
the `Workspace` billable — none are called anywhere.

**Effect:** a customer cannot see or download what they were charged.
See §4 for the full treatment.

**Resolved** by `InvoiceController` — `GET /billing/invoices` (cursor
paginated, open invoices included), `/billing/invoices/upcoming`, and
`/billing/invoices/{id}`. PDFs are Stripe's own hosted documents, so no PDF
dependency was added.

#### 2.3 No payment-method management — RESOLVED via the Billing Portal

No add/update/remove card, no default-payment-method endpoint, and no Stripe
Billing Portal session endpoint (`billingPortalUrl()` is unused). `pm_type`
and `pm_last_four` exist on `workspaces`
(`database/migrations/2026_08_10_172620_add_billable_columns_to_workspaces_table.php`)
but are never surfaced. A customer whose card expires has no in-product way
to fix it.

**Resolved** by `POST /billing/portal`, which returns a Stripe-hosted Billing
Portal URL covering payment methods, receipts, and self-serve cancellation.
Native card-management endpoints were deliberately not built: Stripe's portal
keeps card data off this API entirely. Requires the Customer Portal to be
switched on once in the Stripe dashboard (Settings → Billing → Customer
portal).

#### 2.4 Email verification is not enforced

`User` implements `MustVerifyEmail` (`app/Models/User.php:28`) and the
verify/resend endpoints exist, but no route in the application applies the
`verified` middleware. An unverified address gets the full API, including
workspace creation and checkout.

---

### P1 — expected on any team-plan SaaS

#### 2.5 No audit log

Nothing records who changed a workflow, rotated a secret, changed a member's
role, revoked an API key, or deleted a workspace. There is no `audit_logs`
table and no activity-recording trait. This is normally the first compliance
request from a paying team, and it is far cheaper to add before the mutation
sites multiply further.

#### 2.6 No admin back-office

`PlanGrant` models comped access and `AdminAlertNotification` exists, but
there is no admin route group. Support cannot inspect a workspace, grant
credits, comp a plan, or suspend an abusive tenant without `tinker` on
production. The only admin command is `TestAdminAlertCommand`.

#### 2.7 No upgrade/downgrade preview — RESOLVED

`SubscriptionController::checkout()` swaps the plan directly, and the
customer never saw the prorated amount before it was charged — a predictable
source of disputes, and disputes already have a revocation path
(`handleChargeDisputeCreated`).

`GET .../billing/subscription/preview` (`PreviewSubscriptionSwapAction`) now
returns the prorated invoice Stripe's Create Preview Invoice API would
produce for a given `plan_id`/`interval`, gated by the same availability
guard as checkout, without charging anything. The client can show that
number and let the customer confirm before calling `checkout()`.

#### 2.8 No coupons, promo codes, or tax handling

`allowPromotionCodes()` is never called and there is no discount surface.
Separately, there is no Stripe Tax / `automatic_tax` configuration and no
`tax_id` on `workspaces`. Tax becomes a legal exposure, not a feature
request, the moment the product sells into the EU or UK.

#### 2.9 Dunning stops after one notification — RESOLVED

`handleInvoicePaymentFailed` sends `PaymentFailedNotification` and stops.
There is no grace period, no retry/escalation sequence, and no in-product
"your payment failed" state. Because `activeSubscription()` correctly drops
`past_due`, entitlement disappears silently — the failure mode is a customer
who is locked out with one email as the only explanation.

**Resolved**, with one deliberate exception: there is **no grace period**,
by product decision. Unlike a typical SaaS, a workflow run here costs real
model spend, so an unpaid workspace that kept executing would be a direct
cash loss. `past_due` therefore still withdraws the plan the moment Stripe
reports it.

What was added is the explanation around that, which is what was actually
missing:

- A dunning cycle recorded on the subscription (`dunning_started_at`,
  `dunning_invoice_id`, `dunning_attempts`), surfaced as a `dunning` block on
  `GET /billing` so the frontend can render a banner. `subscription` is null
  by then, so this is the only thing on the response that can explain why the
  plan disappeared.
- Escalating notifications driven by Stripe's own `attempt_count`, naming the
  next retry date and stating plainly that paid features are suspended.
- Recovery: `invoice.payment_succeeded` clears the cycle and notifies — but
  only if one was open, so ordinary renewals stay silent.
- Cancellation: `customer.subscription.deleted` notifies, distinguishing
  "Stripe gave up" from "the customer cancelled".

Retries themselves remain Stripe's job (Smart Retries, configured in the
dashboard); reimplementing them here would duplicate that schedule.

#### 2.10 Usage periods are not billing-cycle aligned

`Workspace::currentUsagePeriod()` uses a calendar month, acknowledged in its
own docblock (`app/Models/Workspaces/Workspace.php:271`). A workspace that
subscribes on the 20th gets a truncated first period, so allowance and
invoice disagree for that customer.

#### 2.11 Public API rate limit ignores the plan

`RateLimiter::for('public-api')` is a flat 60/min for every key
(`app/Providers/AppServiceProvider.php:107`), though `ApiKey` was designed in
`PLAN.md` to be rate-limited by plan. Rate limit is a standard paid-tier
differentiator being given away.

---

### P2 — worth having

#### 2.12 Thin notification catalogue

`NotificationEvent` carries seven cases and its own docblock notes that
billing dunning and trial-expiry cases were deferred. Missing: trial ending,
subscription renewed, subscription canceled, credits low, credits exhausted,
run failed.

#### 2.13 No branded email templates

There is no `app/Mail` directory and no mail views beyond the Pulse vendor
publish. Every transactional email renders as stock Laravel markdown.

#### 2.14 No data export, and account deletion can orphan a workspace

`UserController::destroy()` revokes tokens and calls `delete()`
(`app/Http/Controllers/Api/Internal/V1/User/UserController.php:73`) with no
check for workspaces the user solely owns. A sole owner deleting their
account can leave a workspace with a live Stripe subscription and no owner.
There is also no GDPR-style data export.

#### 2.15 No published API contract

The Bruno collection under `bruno/` is a usable internal substitute, but
there is no OpenAPI spec for external integrators or SDK generation.

#### 2.16 No SSO / SAML / SCIM

Absent, but deliberately: `PLAN.md` defers custom roles and SCIM to the
enterprise phase. Listed for completeness, not as an oversight.

---

### Minor cleanups noticed in passing

- `InsufficientCreditsException`'s docblock claims it "never fires today"
  because `credits_limit` stays null. That is stale — `currentUsagePeriod()`
  now sizes `credits_limit` from `currentPlan()`, and
  `2026_08_20_033228_backfill_usage_period_credit_limits` backfills it.
- `User` still uses Cashier's `Billable` trait (`app/Models/User.php:31`) and
  the `users` table still carries `stripe_id`/`pm_type`/`pm_last_four`/
  `trial_ends_at` from `2026_08_03_184513_create_customer_columns`, even
  though `Cashier::useCustomerModel(Workspace::class)`
  (`app/Providers/AppServiceProvider.php:181`) makes the workspace the
  customer. Dead surface that invites a future bug.

---

## 3. Deep dive — enforcing plan limits

### Why this is P0

Credits are metered and gated; nothing else is. The plan tiers sell three
things — credits, feature flags, and resource caps — and only two of them
are real:

| Sold as | Enforced by | Real? |
| --- | --- | --- |
| Monthly credits | `CreditGate::assertCanStartRun()` | Yes |
| Feature flags | `Plan::hasFeature()` + `FeatureNotAvailableException` | Yes |
| Resource caps | *nothing* | **No** |

A Free workspace is capped at 3 workflows, 1 agent, and 2 members on the
pricing page, and capped at nothing in the API. The upgrade pressure that
those numbers are supposed to create does not exist. Worse, the numbers are
returned to the client in `PlanResource`, so a frontend may render "2 of 3
workflows used" over a limit the server will never apply — a client-side
lock is not enforcement, and the public API bypasses the client entirely.

### The shape to follow

The codebase already has this exact pattern twice, and a third instance
should look like its siblings rather than invent a mechanism:

- **`CreditGate`** (`app/Services/Billing/CreditGate.php`) — a small service
  with an `assert*` method that throws a typed exception, raises an admin
  alert on refusal, and is called at the entry point of the guarded action.
- **`Plan::hasFeature()`** + `FeatureNotAvailableException` — a typed
  exception mapped centrally to a status code in `bootstrap/app.php:97`.

So: a `PlanLimitGate` service, a `PlanLimit` enum, a
`PlanLimitExceededException` mapped to `402` (matching `InsufficientCredits`,
since both mean "this needs a bigger plan") or `403`, and an `assert` call in
each of the three creation paths.

### Sketch

```php
// app/Enums/Billing/PlanLimit.php
enum PlanLimit: string
{
    case Workflows = 'workflows';
    case Agents = 'agents';
    case Members = 'members';
}
```

```php
// app/Models/Billing/Plan.php — sibling of hasFeature()
/**
 * The cap for a limit, or null when unlimited. A seeded -1 means unlimited;
 * a missing key is treated the same way, so adding a new PlanLimit case
 * doesn't retroactively cap every already-seeded plan at zero.
 */
public function limit(PlanLimit $limit): ?int
{
    $value = $this->limits[$limit->value] ?? -1;

    return $value < 0 ? null : (int) $value;
}
```

```php
// app/Services/Billing/PlanLimitGate.php
public function assertCanCreate(Workspace $workspace, PlanLimit $limit): void
{
    $max = $workspace->currentPlan()?->limit($limit);

    if ($max === null) {
        return;
    }

    $used = $this->currentUsage($workspace, $limit);

    if ($used < $max) {
        return;
    }

    throw new PlanLimitExceededException($limit, $max, $used);
}
```

```php
// WorkflowController::store()
$this->requirePermission(Permission::WorkflowManage);
$this->planLimits->assertCanCreate($workspace, PlanLimit::Workflows);
```

### Decisions to make before implementing

1. **Do members count invitations?** If only accepted members count, a
   2-member workspace can hold 50 pending invitations and blow past the cap
   the moment they are accepted. Counting `members + pending invitations`
   against the cap is the safer default.

2. **What happens on downgrade?** A Pro workspace with 40 workflows that
   drops to Starter (cap 25) is instantly over its limit. Blocking *new*
   creation while leaving existing resources alone is the least surprising
   behaviour, and falls out naturally from checking on create only. Deleting
   or locking the excess is a product decision that should be explicit, not
   an accident of implementation.

3. **Do soft-deleted rows count?** `Workflow` uses `SoftDeletes`, so the
   usage count must exclude trashed rows or a user who deletes and recreates
   will hit a phantom cap.

4. **Where else does creation happen?** The three controllers are the obvious
   sites, but template instantiation and any workflow-builder path that
   creates a workflow need the same gate. Putting the assertion in an Action
   rather than the controller — consistent with `PLAN.md`'s "business logic
   lives in `Actions/`" rule — covers every caller including the public API
   and MCP tools.

### Test coverage to add

Per-limit: at cap → refused with the mapped status; one below cap →
succeeds; unlimited plan (`-1`) → succeeds; over cap after a downgrade →
existing rows still readable and updatable, new creation refused.

---

## 4. Deep dive — invoices and billing history

### Why this is P0

"What did you charge me, and when?" is table stakes, and today the API cannot
answer it. `/billing` returns the current plan, usage period, and credit
balance; `/billing/credits` returns the *credit* ledger — which is
consumption, not money. Nothing returns money.

Practical consequences: a customer's finance team cannot retrieve a receipt;
disputes get opened that a visible invoice would have prevented (and
`handleChargeDisputeCreated` shows disputes are already anticipated); and
support has to open the Stripe dashboard for every "what am I paying for"
ticket.

### What Cashier already gives you

`Cashier::useCustomerModel(Workspace::class)`
(`app/Providers/AppServiceProvider.php:181`) means the workspace is the
Stripe customer, so all of this is available on `$workspace` right now:

| Method | Returns |
| --- | --- |
| `invoices()` | Past invoices, newest first |
| `invoicesIncludingPending()` | Includes the open/draft invoice |
| `findInvoice($id)` / `findInvoiceOrFail($id)` | A single invoice |
| `upcomingInvoice()` | The next charge preview — `null` when there is no active subscription |
| `billingPortalUrl($returnUrl)` | A Stripe-hosted portal session |

Each `Invoice` exposes `total()`, `subtotal()`, `tax()`, `date()`, `status`,
`hosted_invoice_url`, and `invoice_pdf`.

### Recommended shape

Add an `InvoiceController` under
`app/Http/Controllers/Api/Internal/V1/Billing/`, following
`CreditController` — `requirePermission(Permission::BillingView)`, an
`InvoiceResource`, `ApiResponse` envelopes:

```php
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('invoices/upcoming', [InvoiceController::class, 'upcoming'])->name('invoices.upcoming');
Route::get('invoices/{invoiceId}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::post('portal', [BillingPortalController::class, 'store'])->name('portal.store');
```

`Permission::BillingView` already exists and already gates the other billing
reads, so no permission changes are needed.

### Three things to get right

1. **Do not add a PDF dependency.** Cashier's `downloadInvoice()` streams a
   locally rendered PDF and needs `dompdf/dompdf`, which is not in
   `composer.json` — and `CLAUDE.md` forbids changing dependencies without
   approval. Returning Stripe's own `invoice_pdf` and `hosted_invoice_url`
   gives the customer a real, Stripe-branded, always-correct document with no
   new package. Prefer that unless self-branded PDFs are an explicit
   requirement.

2. **Guard the un-provisioned workspace.** A workspace with no `stripe_id`
   has never been to checkout. `invoices()` on it will fail rather than
   return empty. Return an empty collection for `index` and `null` for
   `upcoming` in that case — a Free workspace opening the billing screen is
   the common path, not an edge case.

3. **Paginate against Stripe, not in PHP.** `invoices()` is a Stripe API
   call, not a query builder, so `ApiResponse::paginated()` — which requires
   a `LengthAwarePaginator` and will `abort(500)` without one — does not
   apply. Either pass Stripe's `limit`/`starting_after` cursor through, or
   return a plain `ApiResponse::success()` collection with a documented cap.
   Do not fetch everything and slice it.

### The cheaper first move

If the goal is to close the gap in one pass rather than build the surface
properly, the **Billing Portal endpoint alone** covers invoice history,
invoice PDFs, payment-method updates (§2.3), and self-serve cancellation —
i.e. most of §2.2, §2.3, and the customer-facing half of §2.9 — for roughly
one controller method:

```php
public function store(Request $request, Workspace $workspace)
{
    $this->requirePermission(Permission::BillingManage);

    return ApiResponse::success([
        'url' => $workspace->billingPortalUrl($request->validated('return_url')),
    ]);
}
```

It requires the Customer Portal to be configured in the Stripe dashboard, and
it hands the customer off to Stripe's UI rather than keeping them in-product.
Native endpoints are still the better end state — but the portal is the right
thing to ship first, and native invoice reads can follow.

### Test coverage to add

Workspace with no `stripe_id` → empty list, not an error; workspace with
invoices → correct shape and ordering; `upcoming` with no active subscription
→ `null`; a member without `BillingView` → 403; an invoice ID belonging to
another workspace → 404, never another tenant's invoice.

---

## 5. Suggested order

1. ~~**Plan limit enforcement** (§3)~~ — done.
2. ~~**Native invoice endpoints** (§4)~~ — done.
3. ~~**Billing Portal endpoint** (§2.3)~~ — done.
4. ~~**Dunning** (§2.9)~~ — done, minus a grace period by decision.
5. **`verified` middleware** (§2.4) — a routing change, no new code.
6. **Audit log** (§2.5) — gets cheaper the earlier it lands.
7. **Tax configuration** (§2.8) — a legal exposure, not a feature.

The admin back-office follows once the above are in place.
