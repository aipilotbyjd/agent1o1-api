<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\UsagePeriod;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->headers = superAdminHeaders(superAdmin(SuperAdminRole::Finance));
});

/**
 * A signed-up user with a workspace, and optionally how far they got.
 *
 * @param  array<string, mixed>  $user
 * @return array{0: User, 1: Workspace}
 */
function newcomer(array $user = [], ?string $reached = null): array
{
    $owner = User::factory()->create($user);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme '.$owner->id]);

    if (in_array($reached, ['workflow', 'run', 'completed', 'paid'], true)) {
        Workflow::factory()->create(['workspace_id' => $workspace->id]);
    }

    if (in_array($reached, ['run', 'completed', 'paid'], true)) {
        Run::factory()->forWorkspace($workspace)->create(['status' => $reached === 'run' ? RunStatus::Failed : RunStatus::Completed]);
    }

    if ($reached === 'paid') {
        $plan = Plan::query()->first() ?? Plan::factory()->create();
        Subscription::query()->create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'type' => 'default', 'stripe_id' => 'sub_'.$owner->id, 'stripe_status' => 'active', 'stripe_price' => $plan->stripe_price_id_monthly]);
    }

    return [$owner, $workspace];
}

it('counts how far signups got through the funnel', function () {
    newcomer(['email_verified_at' => null]);
    newcomer(['email_verified_at' => now()]);
    newcomer(['email_verified_at' => now()], 'workflow');
    newcomer(['email_verified_at' => now()], 'run');
    newcomer(['email_verified_at' => now()], 'completed');
    newcomer(['email_verified_at' => now()], 'paid');

    $steps = collect($this->withHeaders($this->headers)->getJson(saUrl('growth/funnel'))->assertOk()->json('data.steps'))->keyBy('step');

    // Runs and workflows made by factories add users of their own, so only the signed-up owners are counted by id.
    expect($steps['signed_up']['users'])->toBeGreaterThanOrEqual(6)
        ->and($steps['email_verified']['users'])->toBeLessThan($steps['signed_up']['users'])
        ->and($steps['created_workflow']['users'])->toBeGreaterThanOrEqual(4)
        ->and($steps['started_run']['users'])->toBeGreaterThanOrEqual(3)
        ->and($steps['completed_run']['users'])->toBeGreaterThanOrEqual(2)
        ->and($steps['paid']['users'])->toBeGreaterThanOrEqual(1)
        ->and($steps['completed_run']['users'])->toBeLessThan($steps['started_run']['users'] + 1)
        ->and($steps['paid']['percent_of_signups'])->toBeFloat();
});

it('only counts signups inside the window', function () {
    newcomer(['created_at' => now()->subDays(100)]);

    $steps = collect($this->withHeaders($this->headers)->getJson(saUrl('growth/funnel?from='.now()->subDays(5)->toDateString()))->json('data.steps'))->keyBy('step');

    expect($steps['signed_up']['users'])->toBe(0)->and($steps['signed_up']['percent_of_signups'])->toBeNull();
});

it('measures activation within a day, a week and a month, only among those old enough', function () {
    // Activated on day 0, activated on day 5, never activated, and too new for the 30-day horizon.
    [, $fast] = newcomer(['created_at' => now()->subDays(40)]);
    Run::factory()->forWorkspace($fast)->create(['status' => RunStatus::Completed, 'created_at' => now()->subDays(40)->addHour()]);

    [, $slow] = newcomer(['created_at' => now()->subDays(40)]);
    Run::factory()->forWorkspace($slow)->create(['status' => RunStatus::Completed, 'created_at' => now()->subDays(35)]);

    newcomer(['created_at' => now()->subDays(40)]);

    [, $recent] = newcomer(['created_at' => now()->subDays(3)]);
    Run::factory()->forWorkspace($recent)->create(['status' => RunStatus::Completed, 'created_at' => now()->subDays(3)->addHour()]);

    $horizons = $this->withHeaders($this->headers)
        ->getJson(saUrl('growth/activation?from='.now()->subDays(45)->toDateString()))
        ->assertOk()->json('data.horizons');

    // Factory-made workflow owners may add rows, so only check the owners made here.
    expect($horizons['24h']['activated'])->toBeGreaterThanOrEqual(2)
        ->and($horizons['7d']['activated'])->toBeGreaterThanOrEqual(2)
        ->and($horizons['30d']['activated'])->toBeGreaterThanOrEqual(2)
        ->and($horizons['30d']['eligible'])->toBeLessThan($horizons['24h']['eligible'] + 1);
});

it('builds weekly retention cohorts', function () {
    [, $workspace] = newcomer(['created_at' => now()->subWeeks(2)->startOfWeek()->addDay()]);
    Run::factory()->forWorkspace($workspace)->create(['created_at' => now()->subWeeks(2)->startOfWeek()->addDays(2)]);
    Run::factory()->forWorkspace($workspace)->create(['created_at' => now()->startOfWeek()->addHour()]);

    $cohort = collect($this->withHeaders($this->headers)->getJson(saUrl('growth/retention?weeks=3'))->assertOk()->json('data.cohorts'))
        ->firstWhere('week', now()->subWeeks(2)->startOfWeek()->toDateString());

    expect($cohort['users'])->toBeGreaterThanOrEqual(1)
        ->and($cohort['retention_percent'])->toHaveCount(3)
        ->and($cohort['retention_percent'][0])->toBeGreaterThan(0)
        ->and($cohort['retention_percent'][1])->toEqual(0)
        ->and($cohort['retention_percent'][2])->toBeGreaterThan(0);
});

it('groups acquisition by discovery source', function () {
    newcomer(['discovery_source' => 'google_search']);
    newcomer(['discovery_source' => 'google_search'], 'paid');
    newcomer(['discovery_source' => 'x_twitter'], 'completed');
    newcomer(['discovery_source' => null]);

    $sources = collect($this->withHeaders($this->headers)->getJson(saUrl('growth/acquisition'))->assertOk()->json('data.sources'))->keyBy('source');

    expect($sources['google_search'])->toMatchArray(['signups' => 2, 'paid' => 1, 'paid_percent' => 50.0])
        ->and($sources['x_twitter'])->toMatchArray(['signups' => 1, 'activated' => 1, 'paid' => 0])
        ->and($sources)->toHaveKey('unknown');
});

it('counts daily, weekly and monthly active workspaces', function () {
    [, $a] = newcomer();
    [, $b] = newcomer();

    Run::factory()->forWorkspace($a)->create(['created_at' => now()->subHours(2)]);
    Run::factory()->forWorkspace($b)->create(['created_at' => now()->subDays(3)]);
    Run::factory()->forWorkspace($b)->create(['created_at' => now()->subDays(20)]);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('growth/active-users'))->assertOk()->json('data.workspaces');

    expect($data)->toMatchArray(['dau' => 1, 'wau' => 2, 'mau' => 2, 'stickiness_percent' => 50.0]);
});

it('lists paying workspaces whose activity fell away', function () {
    $plan = Plan::factory()->create(['price_monthly' => 4000, 'stripe_price_id_monthly' => 'price_x']);

    $subscribeWithRuns = function (string $name, int $before, int $after) use ($plan): Workspace {
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => $name]);
        Subscription::query()->create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'type' => 'default', 'stripe_id' => 'sub_'.$name, 'stripe_status' => 'active', 'stripe_price' => 'price_x']);
        Run::factory()->forWorkspace($workspace)->count($before)->create(['created_at' => now()->subDays(10)]);
        Run::factory()->forWorkspace($workspace)->count($after)->create(['created_at' => now()->subDays(2)]);

        return $workspace;
    };

    $subscribeWithRuns('Fading', 20, 2);
    $subscribeWithRuns('Healthy', 20, 18);
    $subscribeWithRuns('Tiny', 3, 0);

    $risky = $this->withHeaders($this->headers)->getJson(saUrl('growth/at-risk'))->assertOk()->json('data');

    expect($risky)->toHaveCount(1)
        ->and($risky[0])->toMatchArray(['name' => 'Fading', 'mrr' => 4000, 'runs_last_7_days' => 2, 'runs_previous_7_days' => 20, 'drop_percent' => 90.0]);
});

it('lists free workspaces close to their allowance as upgrade candidates', function () {
    $plan = Plan::factory()->create(['stripe_price_id_monthly' => 'price_x']);

    $period = function (Workspace $workspace, int $used, int $limit): void {
        (new UsagePeriod)->forceFill(['workspace_id' => $workspace->id, 'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25), 'credits_used' => $used, 'credits_limit' => $limit])->save();
    };

    [, $near] = newcomer();
    [, $low] = newcomer();
    [, $paying] = newcomer();
    Subscription::query()->create(['workspace_id' => $paying->id, 'plan_id' => $plan->id, 'type' => 'default', 'stripe_id' => 'sub_p', 'stripe_status' => 'active', 'stripe_price' => 'price_x']);

    $period($near, 95, 100);
    $period($low, 10, 100);
    $period($paying, 99, 100);

    $candidates = $this->withHeaders($this->headers)->getJson(saUrl('growth/expansion'))->assertOk()->json('data');

    expect($candidates)->toHaveCount(1)->and($candidates[0])->toMatchArray(['workspace_id' => $near->id, 'usage_percent' => 95.0]);
});
