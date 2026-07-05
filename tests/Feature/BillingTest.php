<?php

use App\Enums\Limit;
use App\Enums\SubscriptionStatus;
use App\Exceptions\Billing\InsufficientCreditsException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsagePeriod;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\CreditService;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

// ── Bootstrap free plan ──────────────────────────────────────────────────────

test('creating a workspace bootstraps a free subscription and usage period', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/workspaces', ['name' => 'Test WS']);

    $workspace = $user->ownedWorkspaces()->first();

    expect($workspace->subscription)->not->toBeNull();
    expect($workspace->subscription->status)->toBe(SubscriptionStatus::Active);
    expect($workspace->currentPeriod)->not->toBeNull();
    expect($workspace->currentPeriod->credits_limit)->toBe(1000);
});

// ── Balance formula ──────────────────────────────────────────────────────────

test('credits remaining formula: limit + packs + rollover - used', function () {
    $period = UsagePeriod::factory()->create([
        'credits_limit' => 1000,
        'credits_from_packs' => 200,
        'credits_rolled_over' => 50,
        'credits_used' => 300,
        'is_current' => true,
    ]);

    expect($period->totalAvailable())->toBe(1250);
    expect($period->creditsRemaining())->toBe(950);
});

test('unlimited period returns PHP_INT_MAX for remaining', function () {
    $period = UsagePeriod::factory()->create([
        'credits_limit' => -1,
        'credits_used' => 999999,
        'is_current' => true,
    ]);

    expect($period->isUnlimited())->toBeTrue();
    expect($period->creditsRemaining())->toBe(PHP_INT_MAX);
});

// ── Credit consumption ────────────────────────────────────────────────────────

test('consume deducts from credits_used and is idempotent on same subject', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $sub = Subscription::factory()->create(['workspace_id' => $workspace->id, 'plan_id' => Plan::where('slug', 'free')->first()->id]);
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'subscription_id' => $sub->id,
        'credits_limit' => 1000,
        'credits_used' => 0,
        'is_current' => true,
    ]);

    $creditService = app(CreditService::class);
    $creditService->consume($workspace, 100, $owner);

    $period->refresh();
    expect($period->credits_used)->toBe(100);

    // Second call with same subject should be idempotent
    $creditService->consume($workspace, 100, $owner);

    $period->refresh();
    expect($period->credits_used)->toBe(100);
});

test('insufficient credits throws exception', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    Subscription::factory()->create(['workspace_id' => $workspace->id, 'plan_id' => Plan::where('slug', 'free')->first()->id]);
    UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 10,
        'credits_used' => 10,
        'is_current' => true,
    ]);

    expect(fn () => app(CreditService::class)->checkCredits($workspace, 1))
        ->toThrow(InsufficientCreditsException::class);
});

test('positive admin adjustment raises the available balance', function () {
    $admin = User::factory()->create();
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $sub = Subscription::factory()->create(['workspace_id' => $workspace->id, 'plan_id' => Plan::where('slug', 'free')->first()->id]);
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'subscription_id' => $sub->id,
        'credits_limit' => 1000,
        'credits_used' => 400,
        'is_current' => true,
    ]);

    $creditService = app(CreditService::class);
    $creditService->adjust($workspace, 250, 'Goodwill grant', $admin);

    $period->refresh();
    expect($period->creditsRemaining())->toBe(850); // 1000 + 250 - 400

    // Negative adjustment lowers the balance.
    $creditService->adjust($workspace, -100, 'Correction', $admin);
    $period->refresh();
    expect($period->creditsRemaining())->toBe(750);
});

// ── Plan swap ───────────────────────────────────────────────────────────────────

test('swapping to the same plan and interval does not reset the credit meter', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'pro')->first();
    $sub = Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'billing_interval' => \App\Enums\BillingInterval::Monthly,
        'stripe_subscription_id' => 'sub_test',
    ]);
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'subscription_id' => $sub->id,
        'credits_limit' => $plan->creditsMonthly(),
        'credits_used' => 500,
        'is_current' => true,
    ]);

    app(\App\Services\Billing\SubscriptionService::class)->swap($workspace, $plan, 'monthly');

    // No new period should have been opened, and usage must be preserved.
    expect(UsagePeriod::where('workspace_id', $workspace->id)->count())->toBe(1);
    expect($period->fresh()->credits_used)->toBe(500);
});

// ── Plan seeder ───────────────────────────────────────────────────────────────

test('plan seeder creates 5 plans with correct slugs', function () {
    expect(Plan::count())->toBe(5);
    expect(Plan::where('slug', 'free')->exists())->toBeTrue();
    expect(Plan::where('slug', 'pro')->exists())->toBeTrue();
    expect(Plan::where('slug', 'enterprise')->exists())->toBeTrue();
});

test('yearly price is 10x monthly for paid plans', function () {
    $starter = Plan::where('slug', 'starter')->first();
    expect($starter->price_yearly)->toBe($starter->price_monthly * 10);

    $pro = Plan::where('slug', 'pro')->first();
    expect($pro->price_yearly)->toBe($pro->price_monthly * 10);
});

test('enterprise plan has unlimited credits', function () {
    $enterprise = Plan::where('slug', 'enterprise')->first();
    expect($enterprise->creditsMonthly())->toBe(-1);
    expect($enterprise->isUnlimited(Limit::CreditsMonthly))->toBeTrue();
});

// ── Credit balance endpoint ───────────────────────────────────────────────────

test('balance endpoint returns credits for workspace member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $plan = Plan::where('slug', 'free')->first();
    $sub = Subscription::factory()->create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id]);
    UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'subscription_id' => $sub->id,
        'credits_limit' => 1000,
        'credits_used' => 200,
        'is_current' => true,
    ]);

    $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/credits/balance")
        ->assertOk()
        ->assertJsonPath('data.available', 800)
        ->assertJsonPath('data.used', 200);
});

// ── Plans public endpoint ─────────────────────────────────────────────────────

test('plans endpoint returns active plans sorted', function () {
    $response = $this->getJson('/api/v1/plans');

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(5);
    expect($data[0]['slug'])->toBe('free');
});
