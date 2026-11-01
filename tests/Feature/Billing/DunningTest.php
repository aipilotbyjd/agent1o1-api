<?php

use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Billing\PaymentRecoveredNotification;
use App\Notifications\Billing\SubscriptionCanceledNotification;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

/**
 * The dunning cycle: what happens between a card failing and either
 * recovering or the subscription ending.
 *
 * This app grants **no grace period**: `past_due` stops entitling the moment
 * Stripe reports it, because every workflow run and agent turn costs real
 * model spend. Everything recorded here exists to explain that in-product,
 * never to soften it: the first test below pins that down.
 */
function subscribedWorkspace(string $customerId = 'cus_dunning', string $status = 'active'): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => $customerId])->save();

    $plan = Plan::factory()->create(['credits_monthly' => 5000, 'stripe_price_id_monthly' => 'price_dunning']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_dunning',
        'stripe_status' => $status,
        'stripe_price' => 'price_dunning',
    ]);

    return $workspace->fresh();
}

/**
 * @return array<string, mixed>
 */
function invoiceEventPayload(string $eventId, string $type, string $customerId, array $invoice = []): array
{
    return [
        'id' => $eventId,
        'type' => $type,
        'data' => [
            'object' => array_merge([
                'id' => 'in_dunning',
                'customer' => $customerId,
                'attempt_count' => 1,
            ], $invoice),
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| No grace period
|--------------------------------------------------------------------------
*/

it('withdraws the plan as soon as stripe reports past_due', function () {
    $workspace = subscribedWorkspace(status: 'past_due');
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    expect($workspace->activeSubscription())->toBeNull()
        ->and($workspace->currentPlan()->slug)->toBe('free');
});

/*
|--------------------------------------------------------------------------
| A failed charge
|--------------------------------------------------------------------------
*/

it('records the dunning cycle when a charge fails', function () {
    $workspace = subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning', ['attempt_count' => 1],
    ))->assertOk();

    $subscription = $workspace->subscription('default')->fresh();

    expect($subscription->inDunning())->toBeTrue()
        ->and($subscription->dunning_attempts)->toBe(1)
        ->and($subscription->dunning_invoice_id)->toBe('in_dunning');
});

it('keeps the original start date across repeated failures but tracks the attempt count', function () {
    $workspace = subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning', ['attempt_count' => 1],
    ))->assertOk();

    $startedAt = $workspace->subscription('default')->fresh()->dunning_started_at;

    $this->travel(2)->days();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_2', 'invoice.payment_failed', 'cus_dunning', ['attempt_count' => 3],
    ))->assertOk();

    $subscription = $workspace->subscription('default')->fresh();

    expect($subscription->dunning_started_at->timestamp)->toBe($startedAt->timestamp)
        ->and($subscription->dunning_attempts)->toBe(3);
});

it('escalates the notification wording on a repeat failure', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning', ['attempt_count' => 2],
    ))->assertOk();

    Notification::assertSentTo(
        User::first(),
        PaymentFailedNotification::class,
        function (PaymentFailedNotification $notification): bool {
            return str_contains($notification->title, 'failed again (attempt 2)')
                && str_contains((string) $notification->body, 'suspended until the payment succeeds');
        },
    );
});

it('tells the customer when stripe will retry', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning',
        ['next_payment_attempt' => now()->addDays(3)->timestamp],
    ))->assertOk();

    Notification::assertSentTo(
        User::first(),
        PaymentFailedNotification::class,
        fn (PaymentFailedNotification $n): bool => str_contains((string) $n->body, 'Stripe will try again on'),
    );
});

/**
 * A credit-pack charge is a one-off payment with no subscription behind it.
 * There is no cycle to record, but somebody still needs telling.
 */
it('notifies without a dunning cycle when a one-off charge fails', function () {
    Notification::fake();
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_nosub'])->save();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_nosub',
    ))->assertOk();

    Notification::assertSentTo($workspace->owner, PaymentFailedNotification::class);
});

/*
|--------------------------------------------------------------------------
| Recovery
|--------------------------------------------------------------------------
*/

it('clears the dunning cycle and announces recovery when the charge succeeds', function () {
    Notification::fake();
    $workspace = subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning',
    ))->assertOk();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_paid_1', 'invoice.payment_succeeded', 'cus_dunning',
    ))->assertOk();

    expect($workspace->subscription('default')->fresh()->inDunning())->toBeFalse();

    Notification::assertSentTo(User::first(), PaymentRecoveredNotification::class);
});

/**
 * An ordinary monthly renewal must stay silent: nobody was told anything was
 * wrong, so "payment recovered" would be noise.
 */
it('stays silent when a charge succeeds with no dunning cycle open', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_paid_1', 'invoice.payment_succeeded', 'cus_dunning',
    ))->assertOk();

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Ordinary renewals
|--------------------------------------------------------------------------
*/

it('notifies on an ordinary renewal charge', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_paid_1', 'invoice.payment_succeeded', 'cus_dunning', ['billing_reason' => 'subscription_cycle'],
    ))->assertOk();

    Notification::assertSentTo(User::first(), SubscriptionRenewedNotification::class);
});

it('does not send a renewal notification for the first charge on checkout', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_paid_1', 'invoice.payment_succeeded', 'cus_dunning', ['billing_reason' => 'subscription_create'],
    ))->assertOk();

    Notification::assertNothingSent();
});

it('sends recovery, not a renewal notification, when a renewal closes a dunning cycle', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning',
    ))->assertOk();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_paid_1', 'invoice.payment_succeeded', 'cus_dunning', ['billing_reason' => 'subscription_cycle'],
    ))->assertOk();

    Notification::assertSentTo(User::first(), PaymentRecoveredNotification::class);
    Notification::assertNotSentTo(User::first(), SubscriptionRenewedNotification::class);
});

/*
|--------------------------------------------------------------------------
| The end of the line
|--------------------------------------------------------------------------
*/

it('flags a cancellation that followed failed payments', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning',
    ))->assertOk();

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_deleted_1',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['id' => 'sub_dunning', 'customer' => 'cus_dunning', 'status' => 'canceled']],
    ])->assertOk();

    Notification::assertSentTo(
        User::first(),
        SubscriptionCanceledNotification::class,
        fn (SubscriptionCanceledNotification $n): bool => $n->data['after_failed_payments'] === true,
    );
});

it('does not blame a failed card when the customer simply cancelled', function () {
    Notification::fake();
    subscribedWorkspace();

    $this->postJson('/api/stripe/webhook', [
        'id' => 'evt_deleted_1',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['id' => 'sub_dunning', 'customer' => 'cus_dunning', 'status' => 'canceled']],
    ])->assertOk();

    Notification::assertSentTo(
        User::first(),
        SubscriptionCanceledNotification::class,
        fn (SubscriptionCanceledNotification $n): bool => $n->data['after_failed_payments'] === false,
    );
});

/*
|--------------------------------------------------------------------------
| The billing screen
|--------------------------------------------------------------------------
*/

it('surfaces the dunning cycle on the billing overview', function () {
    $workspace = subscribedWorkspace();
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $this->postJson('/api/stripe/webhook', invoiceEventPayload(
        'evt_fail_1', 'invoice.payment_failed', 'cus_dunning', ['attempt_count' => 2],
    ))->assertOk();

    Passport::actingAs($workspace->owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")
        ->assertOk()
        ->assertJsonPath('data.dunning.attempts', 2)
        ->assertJsonPath('data.dunning.invoice_id', 'in_dunning');
});

it('reports no dunning while payments are healthy', function () {
    $workspace = subscribedWorkspace();

    Passport::actingAs($workspace->owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")
        ->assertOk()
        ->assertJsonPath('data.dunning', null);
});
