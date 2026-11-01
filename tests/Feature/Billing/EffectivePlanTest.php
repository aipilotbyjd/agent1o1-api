<?php

use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * The workspace's *effective* plan (what actually decides its credit
 * allowance and feature access) as opposed to whatever subscription row
 * happens to exist. See `Workspace::currentPlan()`.
 */
function workspaceForPlanResolution(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

function subscribeWorkspace(Workspace $workspace, Plan $plan, string $status, ?string $endsAt = null): Subscription
{
    return Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => $status,
        'stripe_price' => 'price_test',
        'quantity' => 1,
        'ends_at' => $endsAt,
    ]);
}

it('falls back to the configured default plan when the workspace never subscribed', function () {
    $free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $workspace = workspaceForPlanResolution();

    expect($workspace->currentPlan()->id)->toBe($free->id);
    expect($workspace->currentUsagePeriod()->credits_limit)->toBe(100);
});

it('does not hand an unsubscribed workspace an unlimited allowance', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $workspace = workspaceForPlanResolution();

    expect($workspace->availableCredits())->toBe(100);
    expect($workspace->currentUsagePeriod()->credits_limit)->not->toBeNull();
});

it('uses the plan of an active subscription', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    $workspace = workspaceForPlanResolution();
    subscribeWorkspace($workspace, $pro, 'active');

    expect($workspace->currentPlan()->id)->toBe($pro->id);
    expect($workspace->currentUsagePeriod()->credits_limit)->toBe(25000);
});

it('drops a lapsed subscription back to the default plan', function (string $status, ?string $endsAt) {
    $free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    $workspace = workspaceForPlanResolution();

    // Cashier's subscription() returns the row whatever its status, which is
    // how a lapsed workspace used to keep its paid plan indefinitely.
    subscribeWorkspace($workspace, $pro, $status, $endsAt);

    expect($workspace->activeSubscription())->toBeNull();
    expect($workspace->currentPlan()->id)->toBe($free->id);
    expect($workspace->currentUsagePeriod()->credits_limit)->toBe(100);
})->with([
    'canceled past its grace period' => ['canceled', '2020-01-01 00:00:00'],
    'past due' => ['past_due', null],
    'unpaid' => ['unpaid', null],
    'incomplete expired' => ['incomplete_expired', null],
]);

it('treats a trialing subscription as entitling', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    $workspace = workspaceForPlanResolution();
    $subscription = subscribeWorkspace($workspace, $pro, 'trialing');
    $subscription->update(['trial_ends_at' => now()->addDays(7)]);

    expect($workspace->currentPlan()->id)->toBe($pro->id);
});

it('stays unlimited when no default plan is seeded, so a half-seeded install fails open', function () {
    Plan::query()->delete();
    config(['billing.default_plan' => 'free']);

    $workspace = workspaceForPlanResolution();

    expect($workspace->currentPlan())->toBeNull();
    expect($workspace->availableCredits())->toBeNull();
    expect($workspace->currentUsagePeriod()->credits_limit)->toBeNull();
});

it('counts top-up credits toward the available balance', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    $workspace = workspaceForPlanResolution();

    // topup_credits is engine-managed (not in Workspace's #[Fillable] list),
    // only ActivateCreditPackAction and DeductCreditsAction move it.
    $workspace->increment('topup_credits', 250);

    expect($workspace->availableCredits())->toBe(350);
});

it('entitles off a plan grant when no subscription exists', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    $workspace = workspaceForPlanResolution();
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    expect($workspace->currentPlan()->id)->toBe($pro->id);
});

it('keeps entitling off a grant after the subscription lapses', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $starter = Plan::factory()->soldForLifetime()->create(['slug' => 'starter', 'credits_monthly' => 5000]);
    $pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    $workspace = workspaceForPlanResolution();
    subscribeWorkspace($workspace, $pro, 'canceled', '2020-01-01 00:00:00');
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($starter)->active()->create();

    // A lapsed subscription entitles nothing, but the grant was bought
    // outright and survives it.
    expect($workspace->activeSubscription())->toBeNull()
        ->and($workspace->currentPlan()->id)->toBe($starter->id)
        ->and($workspace->currentUsagePeriod()->credits_limit)->toBe(5000);
});

it('takes the more generous of a grant and a subscription', function (int $grantCredits, int $subCredits, int $expected) {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $granted = Plan::factory()->soldForLifetime()->create(['credits_monthly' => $grantCredits]);
    $subscribed = Plan::factory()->create(['credits_monthly' => $subCredits]);

    $workspace = workspaceForPlanResolution();
    subscribeWorkspace($workspace, $subscribed, 'active');
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($granted)->active()->create();

    expect($workspace->currentPlan()->credits_monthly)->toBe($expected);
})->with([
    'grant is richer' => [25000, 5000, 25000],
    'subscription is richer' => [5000, 25000, 25000],
    'equal' => [5000, 5000, 5000],
]);
