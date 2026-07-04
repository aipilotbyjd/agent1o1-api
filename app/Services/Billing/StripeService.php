<?php

namespace App\Services\Billing;

use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeService
{
    private ?StripeClient $client = null;

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret', ''));
    }

    public function createSubscriptionCheckoutSession(array $params): CheckoutSession
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function createBillingPortalSession(array $params): PortalSession
    {
        return $this->client()->billingPortal->sessions->create($params);
    }

    public function createPackCheckoutSession(array $params): CheckoutSession
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function retrieveSubscription(string $id): Subscription
    {
        return $this->client()->subscriptions->retrieve($id);
    }

    /**
     * @throws SignatureVerificationException
     * @throws \UnexpectedValueException
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            throw new \RuntimeException(
                'STRIPE_WEBHOOK_SECRET is not configured. Webhook signature verification cannot proceed.'
            );
        }

        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }
}
