<?php

use App\Models\Billing\Plan;
use App\Services\Billing\MarginFloor;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config([
        'billing.credit_value_usd' => 0.005,
        'billing.overage.credit_value_usd' => 0.0065,
        'billing.margin_floor.monthly_usd_per_credit' => 0.0062,
        'billing.margin_floor.prepaid_usd_per_credit' => 0.0054,
        'billing.margin_floor.payment_fee_rate' => 0.032,
        'billing.intervals.lifetime' => false,
    ]);
});

it('keeps every shipped plan, pack and the overage rate above the margin floor', function () {
    $floor = app(MarginFloor::class);
    $rows = [...$floor->forPacks(), $floor->forOverage()];

    foreach (require database_path('setup/plans.php') as $plan) {
        array_push($rows, ...$floor->forPlan($plan));
    }

    $failing = array_values(array_filter($rows, fn (array $row): bool => ! $row['passes']));

    expect($rows)->not->toBeEmpty()
        ->and($failing)->toBe([]);
});

it('flags a plan that sells credits below what they cost', function () {
    $rows = app(MarginFloor::class)->forPlan([
        'slug' => 'generous',
        'credits_monthly' => 25_000,
        'price_monthly' => 9900,
        'price_yearly' => 99000,
        'enabled_intervals' => ['monthly', 'yearly'],
    ]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['interval' => 'monthly', 'usd_per_credit' => 0.00396, 'passes' => false])
        ->and($rows[1])->toMatchArray(['interval' => 'yearly', 'usd_per_credit' => 0.0033, 'passes' => false])
        ->and($rows[0]['margin_percent'])->toBeLessThan(0);
});

it('only judges the intervals a plan would actually be sold on', function () {
    $rows = app(MarginFloor::class)->forPlan([
        'slug' => 'starter',
        'credits_monthly' => 4_500,
        'price_monthly' => 2900,
        'price_lifetime' => 49000,
        'enabled_intervals' => ['monthly', 'lifetime'],
    ]);

    expect(array_column($rows, 'interval'))->toBe(['monthly']);

    config(['billing.intervals.lifetime' => true]);

    $withLifetime = app(MarginFloor::class)->forPlan([
        'slug' => 'starter',
        'credits_monthly' => 4_500,
        'price_monthly' => 2900,
        'price_lifetime' => 49000,
        'enabled_intervals' => ['monthly', 'lifetime'],
    ]);

    expect(array_column($withLifetime, 'passes', 'interval'))->toBe(['monthly' => true, 'lifetime' => false]);
});

it('skips free plans, whose cost is bounded by their dollar caps instead', function () {
    expect(app(MarginFloor::class)->forPlan(['slug' => 'free', 'credits_monthly' => 100, 'price_monthly' => 0]))->toBe([]);
});

it('fails an overage rate configured at cost, since the card fee then makes every credit a loss', function () {
    config(['billing.overage.credit_value_usd' => 0.005]);

    expect(app(MarginFloor::class)->forOverage()['passes'])->toBeFalse();
});

it('reports the active plans in the database and lists only the failing offers as violations', function () {
    Plan::factory()->create(['slug' => 'healthy', 'credits_monthly' => 4_500, 'price_monthly' => 2900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);
    Plan::factory()->create(['slug' => 'leaky', 'credits_monthly' => 25_000, 'price_monthly' => 9900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);

    $floor = app(MarginFloor::class);

    expect(collect($floor->report())->where('kind', 'plan')->pluck('subject')->all())->toEqualCanonicalizing(['healthy', 'leaky'])
        ->and(collect($floor->violations())->pluck('subject')->all())->toBe(['leaky']);
});

it('fails the margin check command while any offer is below the floor', function () {
    Plan::factory()->create(['slug' => 'leaky', 'credits_monthly' => 25_000, 'price_monthly' => 9900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);

    $this->artisan('billing:margin-check')->assertFailed();
});

it('passes the margin check command when every offer is above the floor', function () {
    Plan::factory()->create(['slug' => 'healthy', 'credits_monthly' => 4_500, 'price_monthly' => 2900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);

    $this->artisan('billing:margin-check')->assertSuccessful();
});

it('refuses a change that pushes an offer below the floor but lets an already-low offer be repaired', function () {
    config(['billing.margin_floor.enforce_changes' => true]);

    $floor = app(MarginFloor::class);
    $healthy = ['slug' => 'pro', 'credits_monthly' => 15_000, 'price_monthly' => 9900, 'enabled_intervals' => ['monthly']];
    $low = [...$healthy, 'price_monthly' => 7000];

    expect(fn () => $floor->assertPlanChangeAllowed($healthy, [...$healthy, 'credits_monthly' => 25_000]))
        ->toThrow(HttpException::class);

    expect(fn () => $floor->assertPlanChangeAllowed($low, [...$low, 'price_monthly' => 8000]))->not->toThrow(Throwable::class)
        ->and(fn () => $floor->assertPlanChangeAllowed($low, [...$low, 'price_monthly' => 6000]))
        ->toThrow(HttpException::class);

    expect(fn () => $floor->assertPackChangeAllowed('big', null, 20_000, 9000))
        ->toThrow(HttpException::class);
});
