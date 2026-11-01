<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\SuperAdmin\RevenueDailySnapshot;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->headers = superAdminHeaders(superAdmin(SuperAdminRole::Finance));

    $this->starter = Plan::factory()->create(['slug' => 'starter', 'name' => 'Starter', 'price_monthly' => 1000, 'price_quarterly' => 2700, 'price_yearly' => 12000, 'stripe_price_id_monthly' => 'price_starter_m', 'stripe_price_id_quarterly' => 'price_starter_q', 'stripe_price_id_yearly' => 'price_starter_y']);
    $this->pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 5000, 'price_quarterly' => 14000, 'price_yearly' => 54000, 'stripe_price_id_monthly' => 'price_pro_m', 'stripe_price_id_quarterly' => 'price_pro_q', 'stripe_price_id_yearly' => 'price_pro_y']);
});

/**
 * A subscription on a fresh workspace.
 *
 * @param  array<string, mixed>  $attributes
 */
function subscribe(Plan $plan, string $priceKey = 'm', array $attributes = [], ?Workspace $workspace = null, array $owner = []): Subscription
{
    $workspace ??= app(WorkspaceService::class)->create(User::factory()->create($owner), ['name' => 'Workspace '.Str::random(5)]);

    return Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(12),
        'stripe_status' => 'active',
        'stripe_price' => "price_{$plan->slug}_{$priceKey}",
        ...$attributes,
    ]);
}

function backdate(Subscription $subscription, $at): Subscription
{
    Subscription::query()->whereKey($subscription->id)->update(['created_at' => $at]);

    return $subscription->refresh();
}

it('is for finance and owners only', function (SuperAdminRole $role, int $status) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('revenue/overview'))->assertStatus($status);
    $this->withHeaders($headers)->getJson(saUrl('growth/funnel'))->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'finance' => [SuperAdminRole::Finance, 200],
    'operator' => [SuperAdminRole::Operator, 403],
    'support' => [SuperAdminRole::Support, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('works out MRR, ARR and ARPU from the active subscriptions', function () {
    subscribe($this->starter);                       // 1000 / month
    subscribe($this->starter, 'y');                  // 12000 / year = 1000
    subscribe($this->pro, 'q');                      // 14000 / quarter = 4667
    subscribe($this->pro, 'm', ['stripe_status' => 'past_due']);  // still revenue: 5000
    subscribe($this->pro, 'm', ['stripe_status' => 'incomplete']);                                   // never paid
    subscribe($this->pro, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);       // gone
    subscribe($this->pro, 'm', ['stripe_status' => 'trialing', 'trial_ends_at' => now()->addDays(7)]); // not paying yet

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/overview'))->assertOk()->json('data');

    expect($data)->toMatchArray([
        'currency' => 'USD',
        'mrr' => 1000 + 1000 + 4667 + 5000,
        'arr' => (1000 + 1000 + 4667 + 5000) * 12,
        'paying_subscribers' => 4,
        'arpu' => (int) round(11667 / 4),
        'trialing' => 1,
        'past_due' => 1,
    ]);
});

it('compares with the start of the period', function () {
    subscribe($this->starter);
    backdate(subscribe($this->pro), now()->subDays(60));

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/overview?days=30'))->json('data');

    expect($data['mrr'])->toBe(6000)
        ->and($data['previous']['mrr'])->toBe(5000)
        ->and($data['net_new_mrr'])->toBe(1000)
        ->and($data['previous']['mrr_change_percent'])->toEqual(20);
});

it('breaks the month down into new, returning and lost revenue', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10));

    $thisMonth = subscribe($this->pro);                                                                    // new: 5000
    $lastMonth = backdate(subscribe($this->starter), now()->subMonth()->startOfMonth()->addDays(3));      // started before: not new now
    $churned = backdate(subscribe($this->starter, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subDays(2)]), now()->subMonths(3)); // lost: 1000

    // A workspace that left and came back.
    $returning = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Boomerang']);
    backdate(subscribe($this->starter, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subMonths(2)], $returning), now()->subMonths(4));
    subscribe($this->starter, 'm', [], $returning);                                                        // returning: 1000

    $month = collect($this->withHeaders($this->headers)->getJson(saUrl('revenue/mrr-movement?months=2'))->assertOk()->json('data.months'))->last();

    expect($month)->toMatchArray([
        'month' => now()->format('Y-m'),
        'starting_mrr' => 2000,   // lastMonth + churned (still paying at month start)
        'new' => 5000,
        'reactivation' => 1000,
        'churned' => -1000,
        'ending_mrr' => 7000,
        'other_change' => 0,
    ]);
});

it('uses a nightly snapshot for a day that has one', function () {
    subscribe($this->pro);

    RevenueDailySnapshot::query()->create(['date' => today()->subDay()->toDateString(), 'plan_slug' => 'pro', 'currency' => 'USD', 'subscribers' => 9, 'mrr' => 123456]);

    $points = $this->withHeaders($this->headers)
        ->getJson(saUrl('revenue/timeseries?from='.today()->subDay()->toDateString().'&to='.today()->toDateString()))
        ->assertOk()->json('data.points');

    expect($points[0])->toMatchArray(['mrr' => 123456, 'subscribers' => 9, 'source' => 'snapshot'])
        ->and($points[1])->toMatchArray(['mrr' => 5000, 'subscribers' => 1, 'source' => 'ledger']);
});

it('stores a snapshot per plan, once per day', function () {
    subscribe($this->starter);
    subscribe($this->starter);
    subscribe($this->pro);

    $this->artisan('revenue:snapshot')->assertSuccessful();
    $this->artisan('revenue:snapshot')->assertSuccessful();

    $rows = RevenueDailySnapshot::query()->orderBy('plan_slug')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->plan_slug->toBe('pro')->subscribers->toBe(1)->mrr->toBe(5000)
        ->and($rows[1])->plan_slug->toBe('starter')->subscribers->toBe(2)->mrr->toBe(2000);
});

it('splits subscribers and revenue by plan', function () {
    subscribe($this->starter);
    subscribe($this->pro);
    subscribe($this->pro);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/plans'))->assertOk()->json('data');

    expect($data['total_mrr'])->toBe(11000)
        ->and($data['plans'][0])->toMatchArray(['plan' => 'pro', 'subscribers' => 2, 'mrr' => 10000, 'share_percent' => 90.9])
        ->and($data['plans'][1])->toMatchArray(['plan' => 'starter', 'subscribers' => 1, 'mrr' => 1000]);
});

it('measures churn in customers and in revenue', function () {
    backdate(subscribe($this->starter), now()->subDays(90));
    backdate(subscribe($this->pro), now()->subDays(90));
    backdate(subscribe($this->starter, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subDays(5)]), now()->subDays(90));
    backdate(subscribe($this->starter, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subDays(60)]), now()->subDays(90));

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/churn?days=30'))->assertOk()->json('data');

    expect($data)->toMatchArray([
        'starting_subscribers' => 3,
        'churned_subscribers' => 1,
        'logo_churn_percent' => 33.33,
        'starting_mrr' => 7000,
        'churned_mrr' => 1000,
        'revenue_churn_percent' => 14.29,
    ])->and($data['by_plan'])->toBe([['plan' => 'starter', 'churned_subscribers' => 1, 'churned_mrr' => 1000]]);
});

it('measures how many trials convert', function () {
    $trial = fn (array $attributes, int $startedDaysAgo) => backdate(subscribe($this->pro, 'm', $attributes), now()->subDays($startedDaysAgo));

    $trial(['trial_ends_at' => now()->subDays(10)], 24);                                                           // converted after 14 days
    $trial(['trial_ends_at' => now()->subDays(10), 'stripe_status' => 'canceled', 'ends_at' => now()->subDays(10)], 24); // lapsed at trial end
    $trial(['trial_ends_at' => now()->addDays(4), 'stripe_status' => 'trialing'], 10);                              // still trialing

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/trials?days=60'))->assertOk()->json('data');

    expect($data)->toMatchArray([
        'trials_started' => 3,
        'trials_finished' => 2,
        'still_trialing' => 1,
        'converted' => 1,
        'conversion_rate_percent' => 50.0,
        'average_days_to_convert' => 14.0,
    ]);
});

it('lists subscriptions that cannot be charged, with the amount at risk', function () {
    subscribe($this->pro, 'm', ['stripe_status' => 'past_due', 'dunning_started_at' => now()->subDays(6), 'dunning_attempts' => 3]);
    subscribe($this->starter, 'y', ['stripe_status' => 'past_due', 'dunning_started_at' => now()->subDays(2), 'dunning_attempts' => 1]);
    subscribe($this->pro);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/failed-payments'))->assertOk()->json('data');

    expect($data['total_at_risk'])->toBe(5000 + 12000)
        ->and($data['subscriptions'])->toHaveCount(2)
        ->and($data['subscriptions'][0])->toMatchArray(['plan' => 'pro', 'days_overdue' => 6, 'amount_at_risk' => 5000, 'attempts' => 3]);
});

it('builds paid-retention cohorts by the month a workspace started paying', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10));

    // Two workspaces started two months ago; one is still paying.
    backdate(subscribe($this->pro), now()->subMonths(2)->startOfMonth()->addDay());
    backdate(subscribe($this->pro, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subMonth()->startOfMonth()->addDays(5)]), now()->subMonths(2)->startOfMonth()->addDay());

    $cohorts = collect($this->withHeaders($this->headers)->getJson(saUrl('revenue/cohorts?months=3'))->assertOk()->json('data.cohorts'))->keyBy('cohort');

    expect($cohorts[now()->subMonths(2)->format('Y-m')])->toMatchArray(['workspaces' => 2, 'retention_percent' => [100.0, 50.0, 50.0]])
        ->and($cohorts[now()->format('Y-m')])->toMatchArray(['workspaces' => 0, 'retention_percent' => [null]]);
});

it('estimates lifetime value from ARPU and churn, and says nothing without churn', function () {
    backdate(subscribe($this->starter, 'm', [], null, ['discovery_source' => 'google_search']), now()->subDays(200));
    backdate(subscribe($this->starter, 'm', ['stripe_status' => 'canceled', 'ends_at' => now()->subDays(30)], null, ['discovery_source' => 'google_search']), now()->subDays(200));
    backdate(subscribe($this->pro, 'm', [], null, ['discovery_source' => 'x_twitter']), now()->subDays(200));

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/ltv'))->assertOk()->json('data');

    $starter = collect($data['by_plan'])->firstWhere('plan', 'starter');
    $pro = collect($data['by_plan'])->firstWhere('plan', 'pro');

    // 1 of 2 customers left in 90 days: 16.67% a month, ARPU 1000.
    expect($starter)->toMatchArray(['paying_subscribers' => 1, 'arpu' => 1000, 'monthly_logo_churn_percent' => 16.67, 'estimated_ltv' => 6000])
        ->and($pro['estimated_ltv'])->toBeNull()
        ->and(collect($data['by_source'])->pluck('source')->all())->toEqualCanonicalizing(['google_search', 'x_twitter']);
});

it('reports credits sold and used', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    CreditPack::query()->create(['workspace_id' => $workspace->id, 'purchased_by' => $workspace->owner_id, 'pack_key' => 'small', 'credits_amount' => 1000, 'price_cents' => 900, 'status' => 'active', 'purchased_at' => now()]);
    CreditPack::query()->create(['workspace_id' => $workspace->id, 'purchased_by' => $workspace->owner_id, 'pack_key' => 'small', 'credits_amount' => 1000, 'price_cents' => 900, 'status' => 'pending']);
    CreditTransaction::query()->create(['workspace_id' => $workspace->id, 'source_type' => 'node_run', 'source_id' => (string) Str::uuid(), 'credits' => 40, 'topup_credits' => 10, 'overage_credits' => 5]);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/credits'))->assertOk()->json('data');

    expect($data)->toMatchArray(['packs_sold' => 1, 'pack_credits_sold' => 1000, 'pack_revenue' => 900, 'credits_consumed' => 40, 'credits_consumed_from_packs' => 10, 'overage_credits' => 5]);
});

it('ranks the biggest customers and the fastest-growing new ones', function () {
    $big = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Big old customer']);
    backdate(subscribe($this->pro, 'm', [], $big), now()->subYear());
    subscribe($this->pro, 'm', [], $big);
    subscribe($this->starter);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('revenue/top-customers'))->assertOk()->json('data');

    expect($data['top'][0])->toMatchArray(['name' => 'Big old customer', 'mrr' => 10000])
        ->and($data['top'])->toHaveCount(2)
        ->and($data['fastest_growing'][0]['mrr'])->toBe(1000);
});

describe('export', function () {
    it('downloads a CSV and audits it', function () {
        subscribe($this->pro);

        $response = $this->withHeaders($this->headers)->get(saUrl('revenue/export?dataset=plans'))->assertOk();

        expect($response->headers->get('content-type'))->toContain('text/csv')
            ->and($response->streamedContent())->toContain('plan,name,subscribers,mrr')->toContain('pro,Pro,1,5000');

        expect(SuperAdminAuditLog::query()->where('action', 'revenue.exported')->count())->toBe(1);
    });

    it('defuses spreadsheet formulas in customer-chosen names', function () {
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => '=HYPERLINK("http://evil.test")']);
        subscribe($this->pro, 'm', [], $workspace);

        $csv = $this->withHeaders($this->headers)->get(saUrl('revenue/export?dataset=subscriptions'))->streamedContent();

        expect($csv)->toContain("\"'=HYPERLINK(")->not->toContain(',"=HYPERLINK');
    });

    it('refuses an unknown dataset', function () {
        $this->withHeaders($this->headers)->getJson(saUrl('revenue/export?dataset=secrets'))->assertUnprocessable();
        $this->withHeaders($this->headers)->getJson(saUrl('revenue/export'))->assertUnprocessable();
    });
});

it('refuses a time range of more than a year', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('revenue/timeseries?from=2020-01-01&to=2026-01-01'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('growth/funnel?from=2020-01-01&to=2026-01-01'))->assertUnprocessable();
});
