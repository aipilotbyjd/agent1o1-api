<?php

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditWallet;
use App\Services\Workspaces\WorkspaceService;

/**
 * Usage periods follow the Stripe billing cycle once Stripe reports it, so
 * credits refill on the billing date, not on the 1st of the month.
 */
function cycleWorkspace(string $customerId): Workspace
{
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->forceFill(['stripe_id' => $customerId])->save();

    return $workspace->fresh();
}

/**
 * @return array<string, mixed>
 */
function cyclePayload(string $eventId, Workspace $workspace, string $subscriptionId, string $priceId, string $status, string $start, string $end, ?string $trialEnd = null): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => $subscriptionId,
            'customer' => $workspace->stripe_id,
            'status' => $status,
            'trial_end' => $trialEnd !== null ? strtotime($trialEnd) : null,
            'items' => ['data' => [[
                'price' => ['id' => $priceId],
                'current_period_start' => strtotime($start),
                'current_period_end' => strtotime($end),
            ]]],
        ]],
    ];
}

function cycleSubscription(Workspace $workspace, string $subscriptionId, string $priceId, string $status, ?string $trialEnd = null): Subscription
{
    return Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => $subscriptionId,
        'stripe_status' => $status,
        'stripe_price' => $priceId,
        'trial_ends_at' => $trialEnd,
    ]);
}

it('does not refill the allowance on the 1st for a subscription started on the 28th', function () {
    $this->travelTo('2026-11-28 10:00:00');
    Plan::factory()->create(['credits_monthly' => 5000, 'stripe_price_id_monthly' => 'price_starter']);
    $workspace = cycleWorkspace('cus_cycle_1');
    cycleSubscription($workspace, 'sub_cycle_1', 'price_starter', 'active');

    $this->postJson('/api/stripe/webhook', cyclePayload('evt_cycle_1', $workspace, 'sub_cycle_1', 'price_starter', 'active', '2026-11-28 10:00:00', '2026-12-28 10:00:00'))->assertOk();
    $november = $workspace->currentUsagePeriod();
    $this->travelTo('2026-12-02 09:00:00');
    $december = $workspace->fresh()->currentUsagePeriod();

    expect($december->id)->toBe($november->id)
        ->and($december->credits_limit)->toBe(5000)
        ->and($december->ends_at->toDateTimeString())->toBe('2026-12-28 10:00:00')
        ->and(CreditGrant::query()->where('workspace_id', $workspace->id)->where('source', CreditGrantSource::Plan)->count())->toBe(1);
});

it('retires the unused free allowance when a paid cycle starts mid-month', function () {
    $this->travelTo('2026-11-10 12:00:00');
    Plan::factory()->create(['credits_monthly' => 5000, 'stripe_price_id_monthly' => 'price_starter']);
    $workspace = cycleWorkspace('cus_cycle_2');
    $freePeriod = $workspace->currentUsagePeriod();
    cycleSubscription($workspace, 'sub_cycle_2', 'price_starter', 'active');

    $this->postJson('/api/stripe/webhook', cyclePayload('evt_cycle_2', $workspace, 'sub_cycle_2', 'price_starter', 'active', '2026-11-10 12:00:00', '2026-12-10 12:00:00'))->assertOk();

    expect($freePeriod->fresh()->ends_at->toDateTimeString())->toBe('2026-11-10 12:00:00')
        ->and(CreditGrant::query()->where('usage_period_id', $freePeriod->id)->sole()->revoked_at)->not->toBeNull()
        ->and($workspace->fresh()->availableCredits())->toBe(5000);
});

it('gives a trialing subscription the plan\'s trial credits, not the full allowance', function () {
    $this->travelTo('2026-11-05 08:00:00');
    Plan::factory()->create(['credits_monthly' => 5000, 'limits' => ['trial_credits' => 1000], 'stripe_price_id_monthly' => 'price_trial']);
    $workspace = cycleWorkspace('cus_cycle_3');
    cycleSubscription($workspace, 'sub_cycle_3', 'price_trial', 'trialing', '2026-11-19 08:00:00');

    $this->postJson('/api/stripe/webhook', cyclePayload('evt_cycle_3', $workspace, 'sub_cycle_3', 'price_trial', 'trialing', '2026-11-05 08:00:00', '2026-11-19 08:00:00', '2026-11-19 08:00:00'))->assertOk();

    expect($workspace->fresh()->availableCredits())->toBe(1000)
        ->and(CreditGrant::query()->where('workspace_id', $workspace->id)->where('source', CreditGrantSource::Trial)->sole()->amount)->toBe(1000);
});

it('refills a yearly subscription monthly rather than once a year', function () {
    $this->travelTo('2026-03-15 00:00:00');
    Plan::factory()->create(['credits_monthly' => 5000, 'stripe_price_id_yearly' => 'price_yearly']);
    $workspace = cycleWorkspace('cus_cycle_4');
    cycleSubscription($workspace, 'sub_cycle_4', 'price_yearly', 'active');

    $this->postJson('/api/stripe/webhook', cyclePayload('evt_cycle_4', $workspace, 'sub_cycle_4', 'price_yearly', 'active', '2026-01-10 00:00:00', '2027-01-10 00:00:00'))->assertOk();
    $period = $workspace->currentUsagePeriod();

    expect($period->starts_at->toDateTimeString())->toBe('2026-03-10 00:00:00')
        ->and($period->ends_at->toDateTimeString())->toBe('2026-04-10 00:00:00')
        ->and($period->credits_limit)->toBe(5000);
});

it('still counts a plan allowance as unused after its grant was revoked or lapsed', function () {
    $workspace = cycleWorkspace('cus_unused');
    $wallet = app(CreditWallet::class);

    $period = $workspace->currentUsagePeriod();
    $period->update(['credits_limit' => 1000, 'credits_used' => 400]);

    $grant = $wallet->ensureAllowance($period->fresh(), CreditGrantSource::Plan);

    $wallet->revoke($grant, 'Billing cycle ended');

    expect($grant->fresh()->remaining)->toBe(0)
        ->and($wallet->unusedPlanAllowance($period->fresh()))->toBe(600);
});
