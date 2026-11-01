<?php

/**
 * Cashier only verifies Stripe's signature when a webhook secret is
 * configured, so the endpoint must refuse to run without one outside
 * local/testing, otherwise anyone could forge billing events.
 */
function stripePayload(): string
{
    return json_encode(['id' => 'evt_signature_test', 'type' => 'ping.test']);
}

function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();

    return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
}

it('refuses to serve the webhook in production without a signing secret', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['cashier.webhook.secret' => null]);

    $this->call('POST', '/api/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], stripePayload())
        ->assertStatus(503);
});

it('rejects a forged or unsigned event when a secret is configured', function (string $kind) {
    app()->detectEnvironment(fn () => 'production');
    config(['cashier.webhook.secret' => 'whsec_test']);

    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($kind === 'wrong secret') {
        $server['HTTP_STRIPE_SIGNATURE'] = stripeSignature(stripePayload(), 'whsec_other');
    }

    $this->call('POST', '/api/stripe/webhook', [], [], [], $server, stripePayload())
        ->assertForbidden();
})->with([
    'no signature' => ['no signature'],
    'wrong secret' => ['wrong secret'],
]);

it('accepts an event signed with the configured secret', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['cashier.webhook.secret' => 'whsec_test']);

    $this->call('POST', '/api/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => stripeSignature(stripePayload(), 'whsec_test'),
    ], stripePayload())->assertOk();
});
