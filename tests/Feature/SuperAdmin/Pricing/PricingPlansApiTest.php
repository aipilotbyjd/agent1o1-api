<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Agents\Agent;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\PricingChangeLog;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);

    config(['billing.default_plan' => 'free']);
    $this->free = Plan::factory()->create(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'stripe_price_id_monthly' => null, 'enabled_intervals' => [], 'sort_order' => 0]);
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'slug' => 'pro', 'credits_monthly' => 1000, 'limits' => ['agents' => 5], 'features' => ['git_sync' => true], 'sort_order' => 1]);
});

it('lists plans in order with subscriber counts, hiding archived ones by default', function () {
    subscribeWorkspaceTo($this->pro);
    subscribeWorkspaceTo($this->pro, 'canceled');
    $archived = Plan::factory()->create(['slug' => 'legacy', 'archived_at' => now(), 'is_active' => false, 'sort_order' => 2]);

    $response = $this->withHeaders($this->headers)->getJson(saUrl('pricing/plans'))->assertOk();

    expect(collect($response->json('data.plans'))->pluck('slug')->all())->toBe(['free', 'pro'])
        ->and($response->json('data.plans.1.subscribers_count'))->toBe(1)
        ->and($response->json('data.plans.0.is_default'))->toBeTrue();

    $this->withHeaders($this->headers)->getJson(saUrl('pricing/plans?archived=1'))
        ->assertOk()->assertJsonFragment(['slug' => $archived->slug]);
});

it('shows a plan with its Stripe ids and price history', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("pricing/plans/{$this->pro->id}"))
        ->assertOk()
        ->assertJsonPath('data.slug', 'pro')
        ->assertJsonPath('data.stripe.prices.monthly', $this->pro->stripe_price_id_monthly)
        ->assertJsonPath('data.features', ['git_sync'])
        ->assertJsonPath('data.price_history', []);
});

it('creates a plan as an inactive draft and logs it', function () {
    $response = $this->withHeaders($this->headers)->postJson(saUrl('pricing/plans'), [
        'name' => 'Team',
        'slug' => 'team',
        'credits_monthly' => 8000,
        'price_monthly' => 4900,
        'limits' => ['workflows' => 50, 'agents' => -1],
        'features' => ['git_sync' => true, 'public_pages' => false],
        'enabled_intervals' => ['monthly'],
    ])->assertCreated();

    $plan = Plan::query()->where('slug', 'team')->sole();

    expect($plan->is_active)->toBeFalse()
        ->and($plan->features)->toBe(['git_sync' => true])
        ->and($plan->price_monthly)->toBe(4900)
        ->and($plan->stripe_price_id_monthly)->toBeNull()
        ->and($response->json('data.is_active'))->toBeFalse()
        ->and(PricingChangeLog::query()->where('plan_id', $plan->id)->where('action', 'created')->count())->toBe(1)
        ->and(SuperAdminAuditLog::query()->where('action', 'pricing.plan.created')->count())->toBe(1);
});

it('rejects a duplicate slug, bad keys and an out-of-range trial', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/plans'), [
        'name' => 'Pro again', 'slug' => 'pro', 'credits_monthly' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('slug');

    $this->withHeaders($this->headers)->postJson(saUrl('pricing/plans'), [
        'name' => 'X', 'slug' => 'x', 'credits_monthly' => 1,
        'limits' => ['teleporters' => 3],
        'features' => ['made_up' => true],
        'trial_days' => 365,
        'enabled_intervals' => ['fortnightly'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['limits', 'features', 'trial_days', 'enabled_intervals.0']);
});

it('updates editable fields, records before and after, and ignores slug and prices', function () {
    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), [
        'reason' => 'Raising the allowance for launch',
        'credits_monthly' => 2000,
        'limits' => ['agents' => 10],
        'badge' => 'Most popular',
        'slug' => 'hacked',
        'price_monthly' => 1,
    ])->assertOk()->assertJsonPath('data.credits_monthly', 2000)->assertJsonPath('data.badge', 'Most popular');

    $plan = $this->pro->fresh();
    $log = PricingChangeLog::query()->where('plan_id', $plan->id)->where('action', 'updated')->sole();

    expect($plan->slug)->toBe('pro')
        ->and($plan->price_monthly)->toBe(2900)
        ->and($log->before)->toBe(['credits_monthly' => 1000, 'limits' => ['agents' => 5], 'badge' => null])
        ->and($log->after)->toBe(['credits_monthly' => 2000, 'limits' => ['agents' => 10], 'badge' => 'Most popular'])
        ->and($log->reason)->toBe('Raising the allowance for launch');
});

it('writes nothing when an update changes nothing', function () {
    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), [
        'reason' => 'No-op edit to check logging',
        'credits_monthly' => 1000,
    ])->assertOk();

    expect(PricingChangeLog::query()->count())->toBe(0);
});

it('requires a reason to update a plan', function () {
    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), ['credits_monthly' => 5])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('activates and deactivates a plan with step-up, and refuses without it', function () {
    $this->pro->forceFill(['is_active' => false])->save();

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/activate"), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/activate"), stepUp($this->finance))
        ->assertOk()->assertJsonPath('data.is_active', true);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/deactivate"), stepUp($this->finance))
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect(PricingChangeLog::query()->where('plan_id', $this->pro->id)->pluck('action')->sort()->values()->all())->toBe(['activated', 'deactivated']);
});

it('never lets the default plan be deactivated, archived or deleted', function () {
    foreach (['deactivate', 'archive'] as $action) {
        $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->free->id}/{$action}"), stepUp($this->finance))
            ->assertUnprocessable();
    }

    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/plans/{$this->free->id}"), stepUp($this->finance))->assertUnprocessable();

    expect($this->free->fresh()->is_active)->toBeTrue()
        ->and($this->free->fresh()->archived_at)->toBeNull()
        ->and(Plan::default()?->slug)->toBe('free');
});

it('archives a plan without taking anything from its subscribers', function () {
    $workspace = subscribeWorkspaceTo($this->pro);

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/archive"), stepUp($this->finance))
        ->assertOk();

    $plan = $this->pro->fresh();

    expect($plan->isArchived())->toBeTrue()
        ->and($plan->is_active)->toBeFalse()
        ->and($workspace->currentPlan()?->id)->toBe($plan->id)
        ->and(Plan::findByStripePriceId($plan->stripe_price_id_monthly)?->id)->toBe($plan->id);

    // Archived plans can't be switched back on without being restored first.
    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$plan->id}/activate"), stepUp($this->finance))->assertUnprocessable();

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$plan->id}/restore"), stepUp($this->finance))
        ->assertOk()->assertJsonPath('data.archived_at', null)->assertJsonPath('data.is_active', false);
});

it('duplicates a plan as an inactive draft with no Stripe ids', function () {
    $this->pro->forceFill(['stripe_product_id' => 'prod_pro'])->save();

    $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/duplicate"), ['name' => 'Pro Plus', 'slug' => 'pro-plus'])
        ->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.stripe.product_id', null)
        ->assertJsonPath('data.stripe.prices.monthly', null);

    $copy = Plan::query()->where('slug', 'pro-plus')->sole();

    expect($copy->credits_monthly)->toBe(1000)
        ->and($copy->limits)->toBe(['agents' => 5])
        ->and($copy->sort_order)->toBeGreaterThan($this->pro->sort_order);
});

it('deletes only a plan nothing refers to', function () {
    subscribeWorkspaceTo($this->pro);
    $unused = Plan::factory()->create(['slug' => 'unused']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/plans/{$this->pro->id}"), stepUp($this->finance))->assertStatus(409);
    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/plans/{$unused->id}"), stepUp($this->finance))->assertOk();

    expect(Plan::query()->where('slug', 'unused')->exists())->toBeFalse()
        ->and(Plan::query()->where('slug', 'pro')->exists())->toBeTrue();

    $grantPlan = Plan::factory()->create(['slug' => 'granted']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Granted']);
    PlanGrant::factory()->forWorkspace($workspace)->forPlan($grantPlan)->active()->create();

    $this->withHeaders($this->headers)->deleteJson(saUrl("pricing/plans/{$grantPlan->id}"), stepUp($this->finance))->assertStatus(409);
});

it('reorders plans and insists on every plan exactly once', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('pricing/plans/reorder'), ['ids' => [$this->pro->id, $this->free->id]])->assertOk();

    expect($this->pro->fresh()->sort_order)->toBe(0)
        ->and($this->free->fresh()->sort_order)->toBe(1);

    $this->withHeaders($this->headers)->postJson(saUrl('pricing/plans/reorder'), ['ids' => [$this->pro->id]])->assertUnprocessable();
});

it('previews the impact of tighter limits, fewer credits and removed features without writing', function () {
    $over = subscribeWorkspaceTo($this->pro);
    $under = subscribeWorkspaceTo($this->pro);
    $elsewhere = subscribeWorkspaceTo($this->free);

    Agent::factory()->count(4)->create(['workspace_id' => $over->id]);
    Agent::factory()->count(1)->create(['workspace_id' => $under->id]);
    Agent::factory()->count(9)->create(['workspace_id' => $elsewhere->id]);

    $response = $this->withHeaders($this->headers)->postJson(saUrl("pricing/plans/{$this->pro->id}/preview"), [
        'credits_monthly' => 400,
        'limits' => ['agents' => 3],
        'features' => ['git_sync' => false],
        'prices' => ['monthly' => 3900],
    ])->assertOk();

    $response->assertJsonPath('data.affected.subscribers', 2)
        ->assertJsonPath('data.credits.delta', -600)
        ->assertJsonPath('data.limits.agents.tightened', true)
        ->assertJsonPath('data.limits.agents.workspaces_over', 1)
        ->assertJsonPath('data.features_removed', ['git_sync'])
        ->assertJsonPath('data.prices.monthly.grandfathered_subscribers', 2);

    expect($this->pro->fresh()->credits_monthly)->toBe(1000)
        ->and(PricingChangeLog::query()->count())->toBe(0);
});

it('lists a plan\'s subscribers and its change history', function () {
    $workspace = subscribeWorkspaceTo($this->pro);

    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), ['reason' => 'Bumping credits for the test', 'credits_monthly' => 1500])->assertOk();

    $this->withHeaders($this->headers)->getJson(saUrl("pricing/plans/{$this->pro->id}/subscribers"))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.workspace.id', $workspace->id);

    $this->withHeaders($this->headers)->getJson(saUrl("pricing/plans/{$this->pro->id}/changes"))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.action', 'updated')->assertJsonPath('data.0.actor.id', $this->finance->id);

    $this->withHeaders($this->headers)->getJson(saUrl('pricing/changes'))->assertOk()->assertJsonCount(1, 'data');
});

it('shows the overview with subscribers and recurring revenue at the price each pays', function () {
    $this->pro->update(['stripe_price_id_yearly' => 'price_pro_year', 'price_yearly' => 12000]);
    subscribeWorkspaceTo($this->pro);
    subscribeWorkspaceTo($this->pro, 'active', 'price_pro_year');

    $response = $this->withHeaders($this->headers)->getJson(saUrl('pricing/overview'))->assertOk();
    $pro = collect($response->json('data.plans'))->firstWhere('slug', 'pro');

    expect($pro['subscribers'])->toBe(2)
        ->and($pro['monthly_recurring_cents'])->toBe(2900 + 1000)
        ->and($response->json('data.intervals'))->toHaveKeys(['monthly', 'quarterly', 'yearly', 'lifetime']);
});

it('lets every role read pricing but only finance and owners change it', function (SuperAdminRole $role, int $read, int $write) {
    $admin = superAdmin($role);
    $headers = superAdminHeaders($admin);

    $this->withHeaders($headers)->getJson(saUrl('pricing/plans'))->assertStatus($read);
    $this->withHeaders($headers)->postJson(saUrl('pricing/plans'), ['name' => 'Z', 'slug' => 'z-'.$role->value, 'credits_monthly' => 1])->assertStatus($write);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 201],
    'finance' => [SuperAdminRole::Finance, 200, 201],
    'operator' => [SuperAdminRole::Operator, 200, 403],
    'support' => [SuperAdminRole::Support, 200, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403],
]);

it('requires authentication', function () {
    $this->getJson(saUrl('pricing/plans'))->assertUnauthorized();
});

it('sets a plan\'s AI cost caps in dollars and its usage limits in whole numbers', function () {
    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), [
        'reason' => 'Capping AI spend per workspace',
        'limits' => ['ai_cost_daily_usd' => 2.5, 'max_concurrent_runs' => 3, 'trial_credits' => 1000],
    ])->assertOk();

    $plan = $this->pro->fresh();
    expect($plan->aiCostDailyUsd())->toBe(2.5)
        ->and($plan->maxConcurrentRuns())->toBe(3)
        ->and($plan->trialCredits())->toBe(1000);

    $this->withHeaders($this->headers)->patchJson(saUrl("pricing/plans/{$this->pro->id}"), [
        'reason' => 'Fractional run count',
        'limits' => ['max_concurrent_runs' => 1.5],
    ])->assertUnprocessable()->assertJsonValidationErrors('limits.max_concurrent_runs');
});
