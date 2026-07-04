<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\PackService;
use App\Services\Billing\StripeService;
use App\Services\Billing\SubscriptionService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private PackService $packService,
        private StripeService $stripeService,
        private NotificationService $notificationService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        } catch (\UnexpectedValueException) {
            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        $type = $event->type;
        $data = $event->data->object->toArray();

        try {
            match ($type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($data),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
                'invoice.payment_failed' => $this->handlePaymentFailed($data),
                'invoice.paid' => Log::info('Stripe invoice.paid received.', ['id' => $event->id]),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook handler failed.', ['type' => $type, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Handler failed.'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $type = $session['metadata']['type'] ?? null;

        if ($type === 'credit_pack') {
            $pack = CreditPack::find($session['metadata']['credit_pack_id'] ?? null);
            if ($pack) {
                $this->packService->activate($pack);
            }

            return;
        }

        if (($session['mode'] ?? null) === 'subscription' && ! empty($session['subscription'])) {
            $subscriptionId = is_string($session['subscription'])
                ? $session['subscription']
                : ($session['subscription']['id'] ?? null);

            if ($subscriptionId) {
                $stripeSub = $this->stripeService->retrieveSubscription($subscriptionId);
                $this->subscriptionService->activateFromCheckout($session, $stripeSub->toArray());
            }
        }
    }

    private function handleSubscriptionUpdated(array $stripeSub): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSub['id'])->first();

        if (! $subscription) {
            return;
        }

        $workspace = $subscription->workspace;

        $subscription->update([
            'status' => $stripeSub['status'],
            'current_period_start' => Carbon::createFromTimestamp($stripeSub['current_period_start']),
            'current_period_end' => Carbon::createFromTimestamp($stripeSub['current_period_end']),
            'canceled_at' => $stripeSub['canceled_at']
                ? Carbon::createFromTimestamp($stripeSub['canceled_at'])
                : null,
        ]);

        $workspace->invalidatePlanCache();
    }

    private function handleSubscriptionDeleted(array $stripeSub): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSub['id'])->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        $subscription->workspace->invalidatePlanCache();
    }

    private function handlePaymentFailed(array $invoice): void
    {
        $customerId = $invoice['customer'] ?? null;
        $subscription = Subscription::where('stripe_customer_id', $customerId)->first();

        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);
        $workspace = $subscription->workspace;
        $workspace->invalidatePlanCache();

        $owner = $workspace->owner;
        if (! $owner) {
            return;
        }

        $this->notificationService->notify(
            user: $owner,
            type: 'billing.payment_failed',
            title: 'Payment failed',
            body: "Your payment for {$workspace->name} failed. Please update your payment method.",
            data: ['workspace_id' => $workspace->id],
            workspace: $workspace,
        );

        $owner->notify(new PaymentFailedNotification($workspace));
    }
}
