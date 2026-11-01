<?php

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * The plan catalog's four billing windows. `Plan::stripePriceId()` and
 * `priceFor()` are unguarded `match`es over this enum, so a case added
 * without an arm is an `UnhandledMatchError` at checkout rather than a
 * compile-time failure, hence the exhaustive sweeps below.
 */
it('resolves a stripe price for every billing interval', function () {
    $plan = Plan::factory()->soldForLifetime()->create();

    foreach (BillingInterval::cases() as $interval) {
        expect($plan->stripePriceId($interval))->toBeString();
    }
});

it('resolves a price amount for every billing interval', function () {
    $plan = Plan::factory()->create([
        'price_monthly' => 100,
        'price_quarterly' => 200,
        'price_yearly' => 300,
        'price_lifetime' => 400,
    ]);

    expect($plan->priceFor(BillingInterval::Monthly))->toBe(100)
        ->and($plan->priceFor(BillingInterval::Quarterly))->toBe(200)
        ->and($plan->priceFor(BillingInterval::Yearly))->toBe(300)
        ->and($plan->priceFor(BillingInterval::Lifetime))->toBe(400);
});

it('treats every interval but lifetime as recurring', function () {
    expect(BillingInterval::Monthly->isRecurring())->toBeTrue()
        ->and(BillingInterval::Quarterly->isRecurring())->toBeTrue()
        ->and(BillingInterval::Yearly->isRecurring())->toBeTrue()
        ->and(BillingInterval::Lifetime->isRecurring())->toBeFalse();
});

it('labels every interval', function () {
    foreach (BillingInterval::cases() as $interval) {
        expect($interval->label())->toBeString()->not->toBeEmpty();
    }
});

it('reports only the intervals the plan has a stripe price for', function () {
    $plan = Plan::factory()->create([
        'stripe_price_id_quarterly' => null,
        'stripe_price_id_lifetime' => null,
    ]);

    expect($plan->availableIntervals())->toBe([BillingInterval::Monthly, BillingInterval::Yearly])
        ->and($plan->isSoldOn(BillingInterval::Quarterly))->toBeFalse()
        ->and($plan->isSoldOn(BillingInterval::Monthly))->toBeTrue();
});

it('sells the free plan on no interval at all', function () {
    Plan::query()->delete();
    test()->syncSetup('plans');

    expect(Plan::query()->where('slug', 'free')->sole()->availableIntervals())->toBe([]);
});

it('prices every paid seeded plan on all four intervals', function () {
    Plan::query()->delete();
    test()->syncSetup('plans');

    $paid = Plan::query()->where('price_monthly', '>', 0)->get();

    expect($paid)->not->toBeEmpty();

    $paid->each(function (Plan $plan): void {
        foreach (BillingInterval::cases() as $interval) {
            expect($plan->priceFor($interval))->toBeGreaterThan(0);
        }
    });
});

it('exposes the new interval pricing through the plans endpoint', function () {
    $plan = Plan::factory()->soldForLifetime()->create([
        'price_quarterly' => 7900,
        'price_lifetime' => 49000,
    ]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/plans")->assertOk();

    $payload = collect($response->json('data.plans'))->firstWhere('slug', $plan->slug);

    expect($payload['price_quarterly'])->toBe(7900)
        ->and($payload['price_lifetime'])->toBe(49000)
        ->and($payload['available_intervals'])->toBe(['monthly', 'quarterly', 'yearly', 'lifetime']);
});
