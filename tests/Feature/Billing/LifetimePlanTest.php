<?php

use App\Actions\Billing\ActivatePlanGrantAction;
use App\Actions\Billing\CheckoutSubscriptionAction;
use App\Actions\Billing\RevokePlanGrantAction;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanGrantStatus;
use App\Enums\Workspaces\Role;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A lifetime plan is bought outright: a `mode=payment` Checkout Session, no
 * Stripe subscription, no `customer.subscription.*` webhook. Entitlement
 * therefore has to come from a `PlanGrant`, which is a second source of truth
 * alongside Cashier. These cover that second path end to end.
 *
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForLifetime(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('entitles a workspace off an active grant with no subscription in sight', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    expect($workspace->activeSubscription())->toBeNull()
        ->and($workspace->currentPlan()->id)->toBe($pro->id)
        ->and($workspace->currentUsagePeriod()->credits_limit)->toBe(25000);
});

it('ignores a pending grant until its payment completes', function () {
    $free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create();

    expect($workspace->currentPlan()->id)->toBe($free->id);
});

it('drops a revoked grant back to the default plan', function () {
    $free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->revoked()->create();

    expect($workspace->currentPlan()->id)->toBe($free->id);
});

it('drops a lapsed fixed-term grant back to the default plan', function () {
    $free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    // expires_at is null for a real lifetime purchase; a comped grant may set
    // one, and once past it must stop entitling.
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->expired()->create();

    expect($workspace->currentPlan()->id)->toBe($free->id);
});

it('keeps the more generous subscription when a cheaper lifetime plan is bought', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $starter = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 5000]);
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $pro->id,
        'type' => 'default',
        'stripe_id' => 'sub_lifetime_mix_1',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($starter)->active()->create();

    // Buying a lifetime Starter must not cost a Pro subscriber their allowance.
    expect($workspace->currentPlan()->id)->toBe($pro->id);
});

it('keeps the more generous lifetime grant when a cheaper subscription is opened', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $starter = Plan::factory()->create(['credits_monthly' => 5000]);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $starter->id,
        'type' => 'default',
        'stripe_id' => 'sub_lifetime_mix_2',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    expect($workspace->currentPlan()->id)->toBe($pro->id);
});

it('prefers the newest grant when a workspace upgrades its lifetime tier', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $starter = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 5000]);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($starter)->active()->create([
        'granted_at' => now()->subYear(),
    ]);
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    expect($workspace->activePlanGrant()->plan_id)->toBe($pro->id)
        ->and($workspace->currentPlan()->id)->toBe($pro->id);
});

it('sizes the usage period to the plan when a grant is activated', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    // No subscription webhook fires for a lifetime purchase, so activation is
    // the only thing that can resize the period, without it the buyer sits
    // on the Free allowance until the month rolls over.
    $period = $workspace->currentUsagePeriod();
    expect($period->credits_limit)->toBe(100);

    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create();

    app(ActivatePlanGrantAction::class)->execute($grant);

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Active)
        ->and($grant->fresh()->granted_at)->not->toBeNull()
        ->and($period->fresh()->credits_limit)->toBe(25000)
        ->and($period->fresh()->plan_id)->toBe($pro->id);
});

it('keeps recorded usage when a grant is activated mid-period', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => 40])->save();

    app(ActivatePlanGrantAction::class)->execute(
        PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create(),
    );

    expect($period->fresh()->credits_used)->toBe(40)
        ->and($period->fresh()->credits_limit)->toBe(25000);
});

it('does not cut the allowance when a lifetime grant is worth less than the subscription', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $starter = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 5000]);
    $pro = Plan::factory()->create(['credits_monthly' => 25000, 'stripe_price_id_monthly' => 'price_pro_m']);

    [$workspace] = ownerWorkspaceForLifetime();

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $pro->id,
        'type' => 'default',
        'stripe_id' => 'sub_lifetime_mix_3',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_m',
    ]);

    $workspace->currentUsagePeriod()->update([
        'plan_id' => $pro->id,
        'subscription_id' => $subscription->id,
        'credits_limit' => 25000,
    ]);

    app(ActivatePlanGrantAction::class)->execute(
        PlanGrant::factory()->forWorkspace($workspace)->forPlan($starter)->create(),
    );

    $period = $workspace->currentUsagePeriod()->fresh();

    expect($period->credits_limit)->toBe(25000)
        // The period must keep pointing at the subscription that still pays for it.
        ->and($period->subscription_id)->toBe($subscription->id);
});

it('activating an already-active grant is a no-op', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create([
        'granted_at' => now()->subMonth(),
    ]);
    $grantedAt = $grant->granted_at;

    app(ActivatePlanGrantAction::class)->execute($grant);

    expect($grant->fresh()->granted_at->timestamp)->toBe($grantedAt->timestamp);
});

it('revoking a grant returns the workspace to its remaining entitlement', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create();
    app(ActivatePlanGrantAction::class)->execute($grant);

    app(RevokePlanGrantAction::class)->execute($grant);

    expect($grant->fresh()->status)->toBe(PlanGrantStatus::Revoked)
        ->and($workspace->currentPlan()->slug)->toBe('free')
        ->and($workspace->currentUsagePeriod()->fresh()->credits_limit)->toBe(100);
});

it('leaves spent credits on the ledger when a grant is revoked mid-period', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForLifetime();

    $grant = PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->create();
    app(ActivatePlanGrantAction::class)->execute($grant);

    $workspace->currentUsagePeriod()->forceFill(['credits_used' => 900])->save();

    app(RevokePlanGrantAction::class)->execute($grant);

    $period = $workspace->currentUsagePeriod()->fresh();

    // Usage above the reinstated limit is not erased: the compute was spent.
    // The balance simply floors at zero.
    expect($period->credits_used)->toBe(900)
        ->and($period->credits_limit)->toBe(100)
        ->and($period->remainingPlanCredits())->toBe(0);
});

it('refuses a subscription checkout for the lifetime interval', function () {
    $plan = Plan::factory()->soldForLifetime()->create();
    [$workspace] = ownerWorkspaceForLifetime();

    expect(fn () => app(CheckoutSubscriptionAction::class)->execute($workspace, $plan, BillingInterval::Lifetime))
        ->toThrow(HttpException::class);
});

it('rejects a lifetime checkout for a plan with no lifetime price', function () {
    [$workspace, $owner] = ownerWorkspaceForLifetime();
    $plan = Plan::factory()->withoutLifetime()->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => 'lifetime',
    ])->assertStatus(422);

    expect(PlanGrant::query()->count())->toBe(0);
});

it('rejects a subscription checkout for an interval the plan is not sold on', function () {
    [$workspace, $owner] = ownerWorkspaceForLifetime();
    $plan = Plan::factory()->create(['stripe_price_id_quarterly' => null]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => 'quarterly',
    ])->assertStatus(422);
});

it('accepts every billing interval as checkout input', function (string $interval) {
    [$workspace, $owner] = ownerWorkspaceForLifetime();
    // No Stripe price for any interval, so each request reaches the action and
    // 422s there rather than failing validation, which is what proves the
    // enum accepts the value.
    $plan = Plan::factory()->create([
        'stripe_price_id_monthly' => null,
        'stripe_price_id_quarterly' => null,
        'stripe_price_id_yearly' => null,
        'stripe_price_id_lifetime' => null,
    ]);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => $interval,
    ])->assertStatus(422);
})->with(['monthly', 'quarterly', 'yearly', 'lifetime']);

it('refuses a duplicate lifetime purchase of a plan the workspace already holds', function () {
    [$workspace, $owner] = ownerWorkspaceForLifetime();
    $plan = Plan::factory()->soldForLifetime()->create();

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($plan)->active()->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => 'lifetime',
    ])->assertStatus(422);
});

it('requires billing manage permission to buy a lifetime plan', function () {
    [$workspace] = ownerWorkspaceForLifetime();
    $plan = Plan::factory()->soldForLifetime()->create();

    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => 'lifetime',
    ])->assertStatus(403);
});
