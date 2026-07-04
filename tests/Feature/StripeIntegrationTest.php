<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\CreditPack;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\PackService;
use App\Services\Billing\StripeService;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Subscription as StripeSubscription;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

// ── Subscription checkout ─────────────────────────────────────────────────────

test('checkout builds a Stripe subscription session with correct params', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $plan->update(['stripe_prices' => ['monthly' => 'price_monthly_123', 'yearly' => 'price_yearly_456']]);

    $fakeSession = CheckoutSession::constructFrom(['url' => 'https://checkout.stripe.com/pay/cs_test_abc']);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createSubscriptionCheckoutSession')
        ->once()
        ->withArgs(function (array $params) use ($workspace) {
            return $params['mode'] === 'subscription'
                && $params['client_reference_id'] === $workspace->id
                && $params['line_items'][0]['price'] === 'price_monthly_123'
                && $params['customer_email'] === 'owner@example.com'
                && isset($params['subscription_data']['trial_period_days']);
        })
        ->andReturn($fakeSession);

    $result = app(SubscriptionService::class)->checkout($workspace, $plan, 'monthly');

    expect($result['url'])->toBe('https://checkout.stripe.com/pay/cs_test_abc');
    expect($result['trial_days'])->toBe($plan->trial_days);
});

test('checkout uses existing stripe_id as customer instead of email', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'stripe_id' => 'cus_existing123']);
    $plan = Plan::where('slug', 'pro')->first();
    $plan->update(['stripe_prices' => ['monthly' => 'price_pro_monthly']]);

    $fakeSession = CheckoutSession::constructFrom(['url' => 'https://checkout.stripe.com/pay/cs_pro']);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createSubscriptionCheckoutSession')
        ->once()
        ->withArgs(fn (array $params) => $params['customer'] === 'cus_existing123' && ! isset($params['customer_email']))
        ->andReturn($fakeSession);

    app(SubscriptionService::class)->checkout($workspace, $plan, 'monthly');
});

test('checkout skips trial for already-trialed plan', function () {
    $owner = User::factory()->create();
    $plan = Plan::where('slug', 'starter')->first();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'trialed_plan_slugs' => [$plan->slug],
    ]);
    $plan->update(['stripe_prices' => ['monthly' => 'price_starter_monthly']]);

    $fakeSession = CheckoutSession::constructFrom(['url' => 'https://checkout.stripe.com/pay/cs_no_trial']);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createSubscriptionCheckoutSession')
        ->once()
        ->withArgs(fn (array $params) => ! isset($params['subscription_data']))
        ->andReturn($fakeSession);

    $result = app(SubscriptionService::class)->checkout($workspace, $plan, 'monthly');

    expect($result['trial_days'])->toBe(0);
});

// ── Billing portal ────────────────────────────────────────────────────────────

test('portalUrl creates a Stripe billing portal session', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'stripe_id' => 'cus_portal456']);

    $fakePortal = PortalSession::constructFrom(['url' => 'https://billing.stripe.com/session/bps_test']);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createBillingPortalSession')
        ->once()
        ->withArgs(fn (array $params) => $params['customer'] === 'cus_portal456')
        ->andReturn($fakePortal);

    $url = app(SubscriptionService::class)->portalUrl($workspace);

    expect($url)->toBe('https://billing.stripe.com/session/bps_test');
});

// ── activateFromCheckout ──────────────────────────────────────────────────────

test('activateFromCheckout creates subscription and transitions usage period', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $plan->update(['stripe_product_id' => 'prod_starter']);

    $stripeSession = [
        'client_reference_id' => $workspace->id,
        'customer' => 'cus_new789',
    ];

    $stripeSub = [
        'id' => 'sub_abc123',
        'status' => 'active',
        'plan' => ['product' => 'prod_starter'],
        'items' => [
            'data' => [[
                'price' => ['id' => 'price_starter_monthly'],
                'plan' => ['interval' => 'month'],
            ]],
        ],
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'trial_end' => null,
    ];

    app(SubscriptionService::class)->activateFromCheckout($stripeSession, $stripeSub);

    $workspace->refresh();
    $subscription = $workspace->subscription;

    expect($subscription)->not->toBeNull();
    expect($subscription->stripe_subscription_id)->toBe('sub_abc123');
    expect($subscription->stripe_customer_id)->toBe('cus_new789');
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->billing_interval)->toBe(BillingInterval::Monthly);
    expect($workspace->stripe_id)->toBe('cus_new789');
});

test('activateFromCheckout with yearly interval sets correct billing_interval', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'pro')->first();
    $plan->update(['stripe_product_id' => 'prod_pro']);

    $stripeSession = ['client_reference_id' => $workspace->id, 'customer' => 'cus_pro'];
    $stripeSub = [
        'id' => 'sub_pro_yearly',
        'status' => 'active',
        'plan' => ['product' => 'prod_pro'],
        'items' => ['data' => [['price' => ['id' => 'price_pro_yearly'], 'plan' => ['interval' => 'year']]]],
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addYear()->timestamp,
        'trial_end' => null,
    ];

    app(SubscriptionService::class)->activateFromCheckout($stripeSession, $stripeSub);

    expect($workspace->fresh()->subscription->billing_interval)->toBe(BillingInterval::Yearly);
});

// ── Pack checkout ─────────────────────────────────────────────────────────────

test('pack checkout creates a Stripe payment session with correct metadata', function () {
    $owner = User::factory()->create();
    $plan = Plan::where('slug', 'starter')->first();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    Subscription::factory()->create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id]);

    config(['billing.packs.small.stripe_price_id' => 'price_pack_small']);

    $fakeSession = CheckoutSession::constructFrom([
        'id' => 'cs_pack_test',
        'url' => 'https://checkout.stripe.com/pay/cs_pack',
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createPackCheckoutSession')
        ->once()
        ->withArgs(function (array $params) {
            return $params['mode'] === 'payment'
                && $params['metadata']['type'] === 'credit_pack'
                && $params['line_items'][0]['price'] === 'price_pack_small';
        })
        ->andReturn($fakeSession);

    $result = app(PackService::class)->checkout($workspace, 'small', $owner);

    expect($result['url'])->toBe('https://checkout.stripe.com/pay/cs_pack');
    expect($result['credit_pack_id'])->not->toBeNull();

    $pack = CreditPack::find($result['credit_pack_id']);
    expect($pack->stripe_checkout_session_id)->toBe('cs_pack_test');
});

// ── Webhook handling ──────────────────────────────────────────────────────────

test('webhook returns 400 on invalid signature', function () {
    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')
        ->once()
        ->andThrow(new SignatureVerificationException('Bad sig'));

    $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'bad'])
        ->assertStatus(400)
        ->assertJsonPath('error', 'Invalid signature.');
});

test('webhook checkout.session.completed activates subscription', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $plan->update(['stripe_product_id' => 'prod_starter_wh']);

    $sessionData = [
        'object' => 'checkout.session',
        'mode' => 'subscription',
        'client_reference_id' => $workspace->id,
        'customer' => 'cus_wh_test',
        'subscription' => 'sub_wh_123',
        'metadata' => [],
    ];

    $subArray = [
        'id' => 'sub_wh_123',
        'status' => 'active',
        'plan' => ['product' => 'prod_starter_wh'],
        'items' => ['data' => [['price' => ['id' => 'price_x'], 'plan' => ['interval' => 'month']]]],
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'trial_end' => null,
    ];

    $fakeEvent = Event::constructFrom([
        'id' => 'evt_test',
        'type' => 'checkout.session.completed',
        'data' => ['object' => $sessionData],
    ]);

    $fakeSub = StripeSubscription::constructFrom($subArray);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')->once()->andReturn($fakeEvent);
    $stripe->shouldReceive('retrieveSubscription')->with('sub_wh_123')->once()->andReturn($fakeSub);

    $this->postJson('/api/v1/webhooks/stripe', $sessionData, ['Stripe-Signature' => 'sig'])
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect($workspace->fresh()->subscription)->not->toBeNull();
    expect($workspace->fresh()->subscription->stripe_subscription_id)->toBe('sub_wh_123');
});

test('webhook customer.subscription.updated updates local subscription status', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $sub = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'stripe_subscription_id' => 'sub_update_123',
        'status' => SubscriptionStatus::Active,
    ]);

    $subData = [
        'object' => 'subscription',
        'id' => 'sub_update_123',
        'status' => 'past_due',
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'canceled_at' => null,
    ];

    $fakeEvent = Event::constructFrom([
        'id' => 'evt_update',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => $subData],
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')->once()->andReturn($fakeEvent);

    $this->postJson('/api/v1/webhooks/stripe', $subData, ['Stripe-Signature' => 'sig'])
        ->assertOk();

    expect($sub->fresh()->status->value)->toBe('past_due');
});

test('webhook customer.subscription.deleted marks subscription canceled', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $sub = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'stripe_subscription_id' => 'sub_delete_123',
        'status' => SubscriptionStatus::Active,
    ]);

    $subData = ['object' => 'subscription', 'id' => 'sub_delete_123', 'status' => 'canceled'];

    $fakeEvent = Event::constructFrom([
        'id' => 'evt_delete',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => $subData],
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')->once()->andReturn($fakeEvent);

    $this->postJson('/api/v1/webhooks/stripe', $subData, ['Stripe-Signature' => 'sig'])
        ->assertOk();

    expect($sub->fresh()->status->value)->toBe('canceled');
    expect($sub->fresh()->canceled_at)->not->toBeNull();
});

test('webhook invoice.payment_failed sets subscription to past_due', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'starter')->first();
    $sub = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'stripe_customer_id' => 'cus_failed_123',
        'status' => SubscriptionStatus::Active,
    ]);

    $invoiceData = ['object' => 'invoice', 'customer' => 'cus_failed_123'];

    $fakeEvent = Event::constructFrom([
        'id' => 'evt_failed',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => $invoiceData],
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('constructWebhookEvent')->once()->andReturn($fakeEvent);

    $this->postJson('/api/v1/webhooks/stripe', $invoiceData, ['Stripe-Signature' => 'sig'])
        ->assertOk();

    expect($sub->fresh()->status->value)->toBe('past_due');
});
