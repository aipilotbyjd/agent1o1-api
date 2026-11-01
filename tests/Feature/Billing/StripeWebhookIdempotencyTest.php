<?php

use App\Actions\Billing\OpenUsagePeriodForSubscriptionAction;
use App\Models\Billing\Plan;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array<string, mixed>
 */
function subscriptionCreatedPayload(string $eventId, string $customerId, string $subscriptionId, string $priceId): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => $subscriptionId,
                'customer' => $customerId,
                'status' => 'active',
                'items' => [
                    'data' => [
                        ['price' => ['id' => $priceId]],
                    ],
                ],
            ],
        ],
    ];
}

it('processes a webhook event exactly once even if redelivered', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_test_123'])->save();

    $plan = Plan::factory()->create(['credits_monthly' => 5000, 'stripe_price_id_monthly' => 'price_test_123']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_123',
    ]);

    $payload = subscriptionCreatedPayload('evt_test_1', 'cus_test_123', 'sub_test_123', 'price_test_123');

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_test_1')->count())->toBe(1);
    expect($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(5000);

    // Stripe redelivers the same event: must not process it twice.
    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_test_1')->count())->toBe(1);
});

it('opens the usage period sized to the plan when a subscription is created', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_test_456'])->save();

    // Consume some credits before subscribing: the pre-existing period should
    // gain a limit, not lose its recorded usage.
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => 10])->save();

    $plan = Plan::factory()->create(['credits_monthly' => 2500, 'stripe_price_id_monthly' => 'price_test_456']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_456',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_456',
    ]);

    $payload = subscriptionCreatedPayload('evt_test_2', 'cus_test_456', 'sub_test_456', 'price_test_456');

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    $fresh = $period->fresh();

    expect($fresh->credits_limit)->toBe(2500)
        ->and($fresh->credits_used)->toBe(10)
        ->and($fresh->plan_id)->toBe($plan->id);
});

it('lets Stripe retry an event whose handler failed instead of reporting it already processed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => 'cus_test_retry'])->save();

    $payload = subscriptionCreatedPayload('evt_test_retry', 'cus_test_retry', 'sub_test_retry', 'price_test_retry');

    $this->mock(OpenUsagePeriodForSubscriptionAction::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('database unavailable'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/stripe/webhook', $payload))->toThrow(RuntimeException::class);

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_test_retry')->exists())->toBeFalse();

    $this->mock(OpenUsagePeriodForSubscriptionAction::class)
        ->shouldReceive('execute')
        ->once();

    $this->postJson('/api/stripe/webhook', $payload)->assertOk();

    expect(ProcessedWebhookEvent::query()->where('stripe_event_id', 'evt_test_retry')->exists())->toBeTrue();
});
