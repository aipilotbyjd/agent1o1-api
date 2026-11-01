<?php

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * Resolving the plan behind a Stripe subscription event. Stripe tells us a
 * price id and nothing else, so the whole entitlement (`subscriptions.plan_id`
 * and the period's `credits_limit`) hangs on reversing that id back to a plan.
 *
 * A lookup that covered only some intervals resolved nothing for the rest,
 * which quietly sized a quarterly subscriber's allowance from the default
 * (Free) plan instead of the one they had just paid for.
 */
function planSyncWorkspace(string $customerId): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => $customerId])->save();

    return $workspace->fresh();
}

function planSyncSubscription(Workspace $workspace, string $subscriptionId, string $priceId): Subscription
{
    return Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => $subscriptionId,
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
    ]);
}

/**
 * @return array<string, mixed>
 */
function planSyncPayload(string $eventId, string $customerId, string $subscriptionId, string $priceId): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => $subscriptionId,
                'customer' => $customerId,
                'status' => 'active',
                'items' => ['data' => [['price' => ['id' => $priceId]]]],
            ],
        ],
    ];
}

it('resolves the plan behind a price on every interval it is sold on', function (BillingInterval $interval) {
    $priceId = "price_sync_{$interval->value}";

    $plan = Plan::factory()->create([
        'credits_monthly' => 7500,
        $interval->stripePriceColumn() => $priceId,
    ]);

    $workspace = planSyncWorkspace("cus_sync_{$interval->value}");
    $subscription = planSyncSubscription($workspace, "sub_sync_{$interval->value}", $priceId);

    $this->postJson('/api/stripe/webhook', planSyncPayload(
        "evt_sync_{$interval->value}",
        $workspace->stripe_id,
        $subscription->stripe_id,
        $priceId,
    ))->assertOk();

    expect($subscription->fresh()->plan_id)->toBe($plan->id)
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(7500);
})->with([
    'monthly' => BillingInterval::Monthly,
    'quarterly' => BillingInterval::Quarterly,
    'yearly' => BillingInterval::Yearly,
]);

it('falls back to the default plan when the price belongs to no plan', function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $workspace = planSyncWorkspace('cus_sync_unknown');
    $subscription = planSyncSubscription($workspace, 'sub_sync_unknown', 'price_not_ours');

    $this->postJson('/api/stripe/webhook', planSyncPayload(
        'evt_sync_unknown',
        $workspace->stripe_id,
        $subscription->stripe_id,
        'price_not_ours',
    ))->assertOk();

    // Metered on the fallback rather than handed an unlimited allowance.
    expect($subscription->fresh()->plan_id)->toBeNull()
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(100);
});

it('finds a plan by a price on any interval', function () {
    $plan = Plan::factory()->create([
        'stripe_price_id_monthly' => 'price_lookup_m',
        'stripe_price_id_quarterly' => 'price_lookup_q',
        'stripe_price_id_yearly' => 'price_lookup_y',
        'stripe_price_id_lifetime' => 'price_lookup_l',
    ]);

    foreach (['price_lookup_m', 'price_lookup_q', 'price_lookup_y', 'price_lookup_l'] as $priceId) {
        expect(Plan::findByStripePriceId($priceId)?->id)->toBe($plan->id);
    }

    expect(Plan::findByStripePriceId('price_nobody_sells'))->toBeNull();
});

it('still resolves a plan that has since been withdrawn from sale', function () {
    // Entitlement follows what was bought, not what is still on the shelf:
    // deactivating a plan must not strand an existing subscriber's allowance.
    $plan = Plan::factory()->create([
        'is_active' => false,
        'stripe_price_id_quarterly' => 'price_withdrawn_q',
    ]);

    expect(Plan::findByStripePriceId('price_withdrawn_q')?->id)->toBe($plan->id);
});

it('maps every billing interval to a distinct plans column', function () {
    $columns = array_map(
        fn (BillingInterval $interval): string => $interval->stripePriceColumn(),
        BillingInterval::cases(),
    );

    expect($columns)->toHaveCount(count(array_unique($columns)))
        ->and($columns)->each->toStartWith('stripe_price_id_');
});
