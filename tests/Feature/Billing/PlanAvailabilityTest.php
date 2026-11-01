<?php

use App\Actions\Billing\CheckoutSubscriptionAction;
use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Withdrawing something from sale. Three independent switches gate a
 * purchase: the plan's `is_active`, the estate-wide
 * `config('billing.intervals')`, and the plan's own `enabled_intervals`,
 * and each must actually stop a checkout, not merely hide a button.
 *
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForAvailability(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

function checkoutAs(User $owner, Workspace $workspace, Plan $plan, string $interval)
{
    Passport::actingAs($owner);

    return test()->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => $plan->id,
        'interval' => $interval,
    ]);
}

it('refuses to sell an interval the plan has switched off', function (string $interval) {
    [$workspace, $owner] = ownerWorkspaceForAvailability();

    // Priced and configured, but withdrawn: the switch, not a missing price,
    // is what must stop the sale.
    $plan = Plan::factory()->soldForLifetime()->sellingOnly([])->create();

    checkoutAs($owner, $workspace, $plan, $interval)->assertStatus(422);
})->with(['monthly', 'quarterly', 'yearly', 'lifetime']);

it('keeps selling the intervals that are still switched on', function () {
    $plan = Plan::factory()->soldForLifetime()->sellingOnly(['monthly', 'yearly'])->create();

    expect($plan->isSoldOn(BillingInterval::Monthly))->toBeTrue()
        ->and($plan->isSoldOn(BillingInterval::Yearly))->toBeTrue()
        ->and($plan->isSoldOn(BillingInterval::Quarterly))->toBeFalse()
        ->and($plan->isSoldOn(BillingInterval::Lifetime))->toBeFalse()
        ->and($plan->availableIntervals())->toBe([BillingInterval::Monthly, BillingInterval::Yearly]);
});

it('drops a switched-off interval out of the plans endpoint', function () {
    [$workspace, $owner] = ownerWorkspaceForAvailability();
    $plan = Plan::factory()->soldForLifetime()->sellingOnly(['monthly'])->create();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/plans")->assertOk();
    $payload = collect($response->json('data.plans'))->firstWhere('slug', $plan->slug);

    expect($payload['available_intervals'])->toBe(['monthly'])
        // The price stays visible so turning it back on needs no re-entry.
        ->and($payload['price_lifetime'])->toBeGreaterThan(0);
});

it('withdraws an interval across every plan via config', function () {
    [$workspace, $owner] = ownerWorkspaceForAvailability();
    $plan = Plan::factory()->soldForLifetime()->create();

    config(['billing.intervals.lifetime' => false]);

    expect($plan->isSoldOn(BillingInterval::Lifetime))->toBeFalse();

    checkoutAs($owner, $workspace, $plan, 'lifetime')->assertStatus(422);

    // The plan's own switch is still on, only the estate-wide one moved.
    expect($plan->enablesInterval(BillingInterval::Lifetime))->toBeTrue();
});

it('withdraws a recurring interval across every plan via config', function () {
    [$workspace, $owner] = ownerWorkspaceForAvailability();
    $plan = Plan::factory()->create();

    config(['billing.intervals.quarterly' => false]);

    checkoutAs($owner, $workspace, $plan, 'quarterly')->assertStatus(422);

    // Only quarterly was withdrawn: the rest of the catalog is untouched.
    expect($plan->isSoldOn(BillingInterval::Monthly))->toBeTrue()
        ->and($plan->isSoldOn(BillingInterval::Yearly))->toBeTrue();
});

it('refuses to sell an inactive plan even when its id is posted directly', function (string $interval) {
    [$workspace, $owner] = ownerWorkspaceForAvailability();
    $plan = Plan::factory()->soldForLifetime()->create(['is_active' => false]);

    // is_active only ever filtered the *listing*; nothing stopped a client
    // POSTing a hidden plan's id straight to checkout and buying it.
    checkoutAs($owner, $workspace, $plan, $interval)->assertStatus(422);
})->with(['monthly', 'quarterly', 'yearly', 'lifetime']);

it('blocks a swap onto a withdrawn interval before touching stripe', function () {
    [$workspace] = ownerWorkspaceForAvailability();
    $plan = Plan::factory()->sellingOnly(['yearly'])->create();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_availability_1',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    // Guarding in the action, not just the controller, is what stops an
    // existing subscriber being swapped onto a withdrawn interval.
    expect(fn () => app(CheckoutSubscriptionAction::class)->execute($workspace, $plan, BillingInterval::Monthly))
        ->toThrow(HttpException::class);
});

it('treats a plan with no enabled_intervals set as selling all of them', function () {
    $plan = Plan::factory()->soldForLifetime()->create(['enabled_intervals' => null]);

    // Rows predating the column must keep their original behaviour rather
    // than silently becoming unsellable.
    expect($plan->availableIntervals())->toBe(BillingInterval::cases());
});

it('explains why an interval cannot be bought', function () {
    $plan = Plan::factory()->soldForLifetime()->create();

    expect($plan->unavailableReasonFor(BillingInterval::Lifetime))->toBeNull();

    config(['billing.intervals.lifetime' => false]);
    expect($plan->unavailableReasonFor(BillingInterval::Lifetime))->toContain('not currently offered');
    config(['billing.intervals.lifetime' => true]);

    $withdrawn = Plan::factory()->soldForLifetime()->sellingOnly(['monthly'])->create();
    expect($withdrawn->unavailableReasonFor(BillingInterval::Lifetime))->toContain('not sold on');

    $unpriced = Plan::factory()->withoutLifetime()->create();
    expect($unpriced->unavailableReasonFor(BillingInterval::Lifetime))->toContain('no Stripe price');

    $inactive = Plan::factory()->soldForLifetime()->create(['is_active' => false]);
    expect($inactive->unavailableReasonFor(BillingInterval::Lifetime))->toContain('not available');
});

it('does not revoke an existing lifetime grant when the interval is withdrawn', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForAvailability();
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    config(['billing.intervals.lifetime' => false]);

    // Withdrawing from sale must not claw back what someone already paid for.
    expect($workspace->currentPlan()->id)->toBe($pro->id)
        ->and($workspace->currentUsagePeriod()->credits_limit)->toBe(25000);
});

it('does not revoke an existing subscription when its plan is deactivated', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['credits_monthly' => 25000]);

    [$workspace] = ownerWorkspaceForAvailability();

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $pro->id,
        'type' => 'default',
        'stripe_id' => 'sub_availability_2',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    $pro->update(['is_active' => false]);

    expect($workspace->currentPlan()->id)->toBe($pro->id)
        ->and($workspace->currentUsagePeriod()->credits_limit)->toBe(25000);
});

it('sells the free plan on nothing at all', function () {
    $free = Plan::factory()->sellingOnly([])->create(['slug' => 'free']);

    expect($free->availableIntervals())->toBe([]);
});
