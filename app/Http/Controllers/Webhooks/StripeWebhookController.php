<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ActivateCreditPackAction;
use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\Referrals\ReferralPaymentSource;
use App\Jobs\Referrals\ProcessReferralPaymentJob;
use App\Jobs\Referrals\ProcessReferralRefundJob;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\Workspaces\Workspace;
use App\Services\Analytics\ProductAnalytics;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Billing\PaymentRecoveredNotification;
use App\Notifications\Billing\SubscriptionCanceledNotification;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use App\Services\Billing\CreditWallet;
use App\Services\Billing\Policies\TrialCreditPolicy;
use App\Services\Billing\StripeCardLookup;
use App\Services\Http\LocalDevelopmentRequest;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Referrals\ReferralPaymentData;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct()
    {
        parent::__construct();

        // Cashier only verifies the signature when a secret is configured, so
        // a missing one would let anyone forge events that grant credits or
        // plans. Refuse to serve the endpoint at all rather than fail open,
        // except for tests and a developer's own machine.
        $this->middleware(function (Request $request, Closure $next) {
            abort_if(
                blank(config('cashier.webhook.secret'))
                && ! app()->environment('testing')
                && ! app(LocalDevelopmentRequest::class)->matches($request),
                503,
                'The Stripe webhook signing secret is not configured.',
            );

            return $next($request);
        });
    }

    /**
     * Guards every event with an idempotency check before Cashier (or our own
     * handlers) process it: Stripe retries webhook delivery, and without
     * this a redelivered event could sync plan/usage-period state twice.
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if ($eventId === null) {
            return $this->missingMethod($payload ?? []);
        }

        $processedEvent = DB::transaction(function () use ($eventId, $payload): ?ProcessedWebhookEvent {
            if (ProcessedWebhookEvent::query()->where('stripe_event_id', $eventId)->lockForUpdate()->exists()) {
                return null;
            }

            return ProcessedWebhookEvent::create([
                'stripe_event_id' => $eventId,
                'type' => $payload['type'] ?? 'unknown',
                'processed_at' => now(),
            ]);
        });

        if ($processedEvent === null) {
            return new Response('Webhook already processed', 200);
        }

        // The event is claimed before it is handled so a concurrent redelivery
        // is skipped, but a claim whose handler failed must not outlive the
        // failure: Stripe's retry would otherwise be answered "already
        // processed" and the event (a paid credit pack, say) lost for good.
        try {
            $response = parent::handleWebhook($request);
        } catch (Throwable $e) {
            $processedEvent->delete();
            $this->recordFailure($eventId, $payload, $e->getMessage());

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            $processedEvent->delete();
            $this->recordFailure($eventId, $payload, 'The handler answered HTTP '.$response->getStatusCode().'.');
        } else {
            $this->resolveFailure($eventId);
        }

        return $response;
    }

    /**
     * Keeps a trace of an event we failed on, so it can be seen and replayed if
     * Stripe gives up retrying. Bookkeeping must never change the outcome of
     * the delivery itself.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordFailure(string $eventId, array $payload, string $error): void
    {
        try {
            StripeWebhookFailure::record($eventId, $payload['type'] ?? 'unknown', $error);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function resolveFailure(string $eventId): void
    {
        try {
            StripeWebhookFailure::resolve($eventId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->checkTrialCard($payload['data']['object'] ?? []);
        $this->syncPlanAndUsagePeriod($payload);
        $this->captureSubscriptionEvent('subscription_started', $payload);

        return $response;
    }

    /**
     * Billing funnel events for PostHog. Attributed to the workspace owner,
     * who is the person that subscribed. Never fails the webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    private function captureSubscriptionEvent(string $event, array $payload, ?object $workspace = null): void
    {
        try {
            $object = $payload['data']['object'] ?? [];
            $customerId = $object['customer'] ?? null;
            $workspace ??= $customerId !== null ? Cashier::findBillable($customerId) : null;

            if (! $workspace instanceof Workspace) {
                return;
            }

            app(ProductAnalytics::class)->capture(
                (string) $workspace->owner_id,
                $event,
                ['stripe_status' => $object['status'] ?? null, 'is_trial' => ($object['status'] ?? null) === 'trialing'],
                (string) $workspace->getKey(),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * One trial per card: when a trial starts, its card is compared with
     * every earlier trial, and prepaid cards are refused. A refused card ends
     * the trial at once, so Stripe charges for the first period now. Never
     * fails the webhook.
     *
     * @param  array<string, mixed>  $object
     */
    private function checkTrialCard(array $object): void
    {
        if (($object['status'] ?? null) !== 'trialing' || ! isset($object['customer'], $object['id'])) {
            return;
        }

        try {
            $workspace = Cashier::findBillable($object['customer']);
            $subscription = $workspace?->subscriptions()->where('stripe_id', $object['id'])->first();

            if ($workspace === null || $subscription === null) {
                return;
            }

            $card = app(StripeCardLookup::class)->forSubscription($object);
            $reason = app(TrialCreditPolicy::class)->checkCard($workspace, $subscription, $card['fingerprint'], $card['funding']);

            if ($reason !== null) {
                $subscription->endTrial();

                Log::info('Trial ended at start', ['workspace_id' => $workspace->id, 'reason' => $reason]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->syncPlanAndUsagePeriod($payload);

        return $response;
    }

    /**
     * Fulfils our two one-off (`mode=payment`) Checkout flows: credit-pack
     * top-ups and lifetime plan purchases. Subscription checkouts land here
     * too (Cashier's own sync handles those via
     * `customer.subscription.created`) so this acts only on sessions
     * carrying the matching metadata `type` we set at checkout.
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];

        // A delayed payment method (a bank debit) completes the session
        // before the money has moved: fulfilment waits for
        // `checkout.session.async_payment_succeeded` instead.
        if (($session['payment_status'] ?? null) === 'unpaid') {
            return new Response('Webhook Handled');
        }

        return $this->fulfilCheckoutSession($session);
    }

    /**
     * The delayed payment behind a completed session has now settled.
     */
    protected function handleCheckoutSessionAsyncPaymentSucceeded(array $payload): Response
    {
        return $this->fulfilCheckoutSession($payload['data']['object'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function fulfilCheckoutSession(array $session): Response
    {
        $metadata = $session['metadata'] ?? [];
        $paymentIntentId = $session['payment_intent'] ?? null;

        match ($metadata['type'] ?? null) {
            'credit_pack' => $this->fulfilCreditPack($metadata, $paymentIntentId),
            'plan_grant' => $this->fulfilPlanGrant($metadata, $paymentIntentId),
            default => null,
        };

        $this->recordReferralCheckoutPayment($session, $metadata, $paymentIntentId);

        return new Response('Webhook Handled');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fulfilCreditPack(array $metadata, ?string $paymentIntentId): void
    {
        if (! isset($metadata['credit_pack_id'])) {
            return;
        }

        $pack = CreditPack::find($metadata['credit_pack_id']);

        if ($pack === null) {
            return;
        }

        $pack->update(['stripe_payment_intent_id' => $paymentIntentId]);

        app(ActivateCreditPackAction::class)->execute($pack);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fulfilPlanGrant(array $metadata, ?string $paymentIntentId): void
    {
        if (! isset($metadata['plan_grant_id'])) {
            return;
        }

        $grant = PlanGrant::find($metadata['plan_grant_id']);

        if ($grant === null) {
            return;
        }

        $grant->update(['stripe_payment_intent_id' => $paymentIntentId]);

        app(ActivatePlanGrantAction::class)->execute($grant);
    }

    /**
     * A refunded lifetime purchase has to stop entitling the workspace. The
     * grant has no expiry and no subscription status to lapse, so nothing
     * else would ever withdraw it.
     */
    protected function handleChargeRefunded(array $payload): Response
    {
        $charge = $payload['data']['object'] ?? [];

        $this->revokeGrantForPaymentIntent($charge['payment_intent'] ?? null);
        $this->revokeCreditsForPaymentIntent($charge['payment_intent'] ?? null, 'Payment refunded');

        $fullyRefunded = ($charge['refunded'] ?? false) === true
            || (isset($charge['amount'], $charge['amount_refunded']) && $charge['amount_refunded'] >= $charge['amount']);

        $this->withdrawReferralRewards($charge['payment_intent'] ?? null, $fullyRefunded, 'Payment refunded');

        return new Response('Webhook Handled');
    }

    /**
     * Stripe pulls the funds as soon as a dispute is opened, so the grant is
     * withdrawn now rather than on resolution.
     */
    protected function handleChargeDisputeCreated(array $payload): Response
    {
        $paymentIntentId = $payload['data']['object']['payment_intent'] ?? null;

        $this->revokeGrantForPaymentIntent($paymentIntentId);
        $this->revokeCreditsForPaymentIntent($paymentIntentId, 'Payment disputed');
        $this->withdrawReferralRewards($paymentIntentId, true, 'Payment disputed');

        return new Response('Webhook Handled');
    }

    /**
     * Takes back the credits a refunded or disputed payment bought: the
     * unspent part of the wallet grant it paid for is revoked, and whatever
     * was already spent becomes debt that the workspace's next grant pays
     * first. A customer cannot buy credits, spend them and get the money back.
     */
    private function revokeCreditsForPaymentIntent(?string $paymentIntentId, string $reason): void
    {
        if ($paymentIntentId === null) {
            return;
        }

        CreditGrant::query()
            ->where('stripe_reference', $paymentIntentId)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (CreditGrant $grant): int => app(CreditWallet::class)->revoke($grant, $reason, spentBecomesDebt: true));
    }

    private function revokeGrantForPaymentIntent(?string $paymentIntentId): void
    {
        if ($paymentIntentId === null) {
            return;
        }

        $grant = PlanGrant::query()->where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($grant === null) {
            return;
        }

        app(RevokePlanGrantAction::class)->execute($grant);
    }

    /**
     * Stripe fires this on every failed invoice charge attempt, including each
     * automatic retry. The subscription's own status transition (to
     * `past_due`) is synced by Cashier's handler; this records the dunning
     * cycle and tells the workspace what happened.
     *
     * No grace period is granted here or anywhere else: `past_due` stops
     * entitling the moment Stripe reports it, because every workflow run and
     * agent turn costs real model spend. The state recorded below exists to
     * *explain* that in-product, not to soften it.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $invoice = $payload['data']['object'] ?? [];
        $customerId = $invoice['customer'] ?? null;

        if ($customerId === null) {
            return new Response('Webhook Handled');
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace === null) {
            return new Response('Webhook Handled');
        }

        $attempts = max((int) ($invoice['attempt_count'] ?? 1), 1);
        $nextAttemptAt = isset($invoice['next_payment_attempt'])
            ? Carbon::createFromTimestampUTC($invoice['next_payment_attempt'])
            : null;

        // Resolved through the workspace rather than the invoice's own
        // subscription field, whose shape has moved between Stripe API
        // versions. Null for a one-off charge (a credit pack), which has no
        // dunning cycle to record but still warrants telling someone.
        $workspace->subscription('default')?->markDunning(
            $invoice['id'] ?? null,
            $attempts,
        );

        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->dispatch(
            $dispatcher->ownersAndAdmins($workspace),
            new PaymentFailedNotification($workspace, $attempts, $invoice['id'] ?? null, $nextAttemptAt),
        );

        return new Response('Webhook Handled');
    }

    /**
     * Collection succeeded. Closes any open dunning cycle and says so, but
     * only if there was one, so an ordinary monthly renewal doesn't generate a
     * "payment recovered" notification nobody was waiting for. An ordinary
     * renewal (Stripe's `billing_reason: subscription_cycle`, with no
     * dunning cycle to close) gets its own quieter notification instead: the
     * first charge on checkout (`subscription_create`) stays silent, since
     * the checkout response already confirmed it.
     */
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        $response = parent::handleInvoicePaymentSucceeded($payload);

        $invoice = $payload['data']['object'] ?? [];
        $customerId = $invoice['customer'] ?? null;

        if ($customerId === null) {
            return $response;
        }

        $workspace = Cashier::findBillable($customerId);
        $recovered = $workspace?->subscription('default')?->clearDunning() ?? false;

        if ($workspace === null) {
            return $response;
        }

        $dispatcher = app(NotificationDispatcher::class);

        if ($recovered) {
            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new PaymentRecoveredNotification($workspace),
            );
        } elseif (($invoice['billing_reason'] ?? null) === 'subscription_cycle') {
            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new SubscriptionRenewedNotification($workspace, $invoice['id'] ?? null),
            );
        }

        $this->recordReferralInvoicePayment($workspace, $invoice);

        return $response;
    }

    /**
     * The end of the line. Stripe deletes a subscription both when a customer
     * cancels and when it gives up after exhausting its retry schedule: an
     * open dunning cycle is what separates the two, so it's read before
     * Cashier's handler clears the row's status.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $workspace = $customerId !== null ? Cashier::findBillable($customerId) : null;
        $subscription = $workspace?->subscription('default');
        $afterFailedPayments = $subscription?->inDunning() ?? false;

        $response = parent::handleCustomerSubscriptionDeleted($payload);
        $this->captureSubscriptionEvent('subscription_canceled', $payload, $workspace);

        if ($workspace !== null) {
            $subscription?->clearDunning();

            $dispatcher = app(NotificationDispatcher::class);

            $dispatcher->dispatch(
                $dispatcher->ownersAndAdmins($workspace),
                new SubscriptionCanceledNotification($workspace, $afterFailedPayments),
            );
        }

        return $response;
    }

    private function syncPlanAndUsagePeriod(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $stripeSubscriptionId = $payload['data']['object']['id'] ?? null;
        $stripePriceId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;

        if ($customerId === null || $stripeSubscriptionId === null) {
            return;
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace === null) {
            return;
        }

        $plan = $stripePriceId !== null
            ? Plan::findByStripePriceId($stripePriceId)
            : null;

        if ($plan !== null) {
            $workspace->subscriptions()
                ->where('stripe_id', $stripeSubscriptionId)
                ->update(['plan_id' => $plan->id]);
        }

        $this->syncBillingCycle($workspace, $stripeSubscriptionId, $payload['data']['object']);

        app(OpenUsagePeriodForSubscriptionAction::class)->execute($workspace, $plan, $stripeSubscriptionId);
    }

    /**
     * Copies the subscription's current billing cycle from the Stripe
     * object, so usage periods follow the billing date. Stripe moved
     * `current_period_start`/`_end` from the subscription onto its items in
     * newer API versions; both shapes are read.
     *
     * @param  array<string, mixed>  $object
     */
    private function syncBillingCycle(Workspace $workspace, string $stripeSubscriptionId, array $object): void
    {
        $start = $object['current_period_start'] ?? $object['items']['data'][0]['current_period_start'] ?? null;
        $end = $object['current_period_end'] ?? $object['items']['data'][0]['current_period_end'] ?? null;

        if (! is_numeric($start) || ! is_numeric($end)) {
            return;
        }

        $workspace->subscriptions()
            ->where('stripe_id', $stripeSubscriptionId)
            ->update([
                'current_period_starts_at' => Carbon::createFromTimestampUTC((int) $start),
                'current_period_ends_at' => Carbon::createFromTimestampUTC((int) $end),
            ]);
    }

    /**
     * Hands a paid subscription invoice to the referral program. Tax is
     * taken out (Stripe's `total` minus `total_excluding_tax`) and a $0
     * invoice (a trial start) never counts as a payment. Failures are
     * reported, never allowed to fail the webhook.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function recordReferralInvoicePayment(Workspace $workspace, array $invoice): void
    {
        $this->safelyForReferrals(function () use ($workspace, $invoice): void {
            $tax = max(0, (int) ($invoice['total'] ?? 0) - (int) ($invoice['total_excluding_tax'] ?? $invoice['total'] ?? 0));
            $amount = max(0, (int) ($invoice['amount_paid'] ?? 0) - $tax);

            if ($amount <= 0 || ! isset($invoice['id'])) {
                return;
            }

            $line = $invoice['lines']['data'][0] ?? [];
            $priceId = $line['price']['id'] ?? $line['pricing']['price_details']['price'] ?? null;
            $plan = $priceId !== null ? Plan::findByStripePriceId($priceId) : null;
            $interval = $plan !== null
                ? collect(BillingInterval::cases())->first(fn (BillingInterval $interval): bool => $plan->stripePriceId($interval) === $priceId)
                : null;

            ProcessReferralPaymentJob::dispatch(new ReferralPaymentData(
                workspaceId: $workspace->id,
                reference: $invoice['id'],
                // Newer Stripe API versions moved the intent under `payments`.
                paymentIntentId: $invoice['payment_intent'] ?? $invoice['payments']['data'][0]['payment']['payment_intent'] ?? null,
                source: ReferralPaymentSource::Subscription,
                amountCents: $amount,
                currency: $invoice['currency'] ?? 'usd',
                planId: $plan?->id,
                interval: $interval,
            ));
        });
    }

    /**
     * Hands a one-off Checkout purchase (credit pack or lifetime plan) to
     * the referral program, keyed by its payment intent.
     *
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $metadata
     */
    private function recordReferralCheckoutPayment(array $session, array $metadata, ?string $paymentIntentId): void
    {
        $this->safelyForReferrals(function () use ($session, $metadata, $paymentIntentId): void {
            $purchase = match ($metadata['type'] ?? null) {
                'credit_pack' => isset($metadata['credit_pack_id']) ? CreditPack::find($metadata['credit_pack_id']) : null,
                'plan_grant' => isset($metadata['plan_grant_id']) ? PlanGrant::find($metadata['plan_grant_id']) : null,
                default => null,
            };

            $amount = max(0, (int) ($session['amount_total'] ?? 0) - (int) ($session['total_details']['amount_tax'] ?? 0));

            if ($purchase === null || $paymentIntentId === null || $amount <= 0) {
                return;
            }

            ProcessReferralPaymentJob::dispatch(new ReferralPaymentData(
                workspaceId: $purchase->workspace_id,
                reference: $paymentIntentId,
                paymentIntentId: $paymentIntentId,
                source: $purchase instanceof PlanGrant ? ReferralPaymentSource::Lifetime : ReferralPaymentSource::CreditPack,
                amountCents: $amount,
                currency: $session['currency'] ?? 'usd',
                planId: $purchase instanceof PlanGrant ? $purchase->plan_id : null,
                interval: $purchase instanceof PlanGrant ? BillingInterval::Lifetime : null,
            ));
        });
    }

    private function withdrawReferralRewards(?string $paymentIntentId, bool $fullyRefunded, string $reason): void
    {
        if ($paymentIntentId === null) {
            return;
        }

        $this->safelyForReferrals(fn () => ProcessReferralRefundJob::dispatch($paymentIntentId, $fullyRefunded, $reason));
    }

    /**
     * The referral program rides along on billing webhooks; a bug in it
     * must never turn a Stripe delivery into a 5xx and a retry storm.
     */
    private function safelyForReferrals(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
