<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditGrantSource;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Services\Billing\CreditWallet;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('returns null subscription and the current usage period when unsubscribed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing");

    $response->assertOk();
    expect($response->json('data.subscription'))->toBeNull();
    expect($response->json('data.usage_period.credits_used'))->toBe(0);
});

it('returns the subscription with its plan when subscribed', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $plan = Plan::factory()->create(['slug' => 'pro']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_overview',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing");

    $response->assertOk();
    expect($response->json('data.subscription.plan.slug'))->toBe('pro');
});

it('lists credit transaction history newest first, paginated', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 3, 'first');
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5, 'second');

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credits");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.reason'))->toBe('second');
    expect($response->json('meta.total'))->toBe(2);
});

it('returns the active lifetime grant and the plan that actually applies', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->soldForLifetime()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    PlanGrant::factory()->forWorkspace($workspace)->forPlan($pro)->active()->create();

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")->assertOk();

    // No subscription exists, so `subscription.plan` cannot answer "what plan
    // am I on" - `current_plan` is what the screen must render.
    expect($response->json('data.subscription'))->toBeNull()
        ->and($response->json('data.plan_grant.status'))->toBe('active')
        ->and($response->json('data.plan_grant.source'))->toBe('lifetime_purchase')
        ->and($response->json('data.plan_grant.plan.slug'))->toBe('pro')
        ->and($response->json('data.current_plan.slug'))->toBe('pro');
});

it('reports the default plan as current when nothing entitles the workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")->assertOk();

    expect($response->json('data.plan_grant'))->toBeNull()
        ->and($response->json('data.current_plan.slug'))->toBe('free');
});

it('breaks the balance down by source in spend order and explains withheld free credits', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $owner = User::factory()->create();
    app(WorkspaceService::class)->create($owner, ['name' => 'First']);
    $second = app(WorkspaceService::class)->create($owner, ['name' => 'Second']);
    app(CreditWallet::class)->grant($second, CreditGrantSource::Topup, 500);
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$second->id}/billing");

    $response->assertOk()
        ->assertJsonPath('data.credits_available', 500)
        ->assertJsonPath('data.credit_grants.0.source', 'topup')
        ->assertJsonPath('data.credit_grants.0.remaining', 500)
        ->assertJsonPath('data.credit_debt', 0)
        ->assertJsonPath('data.free_credits.eligible', false)
        ->assertJsonPath('data.free_credits.reason', 'Free credits are included with your first workspace only. Choose a plan or buy credits for this one.');
});

it('lists which credits paid for each charge', function () {
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100]);
    config(['billing.default_plan' => 'free']);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    app(CreditWallet::class)->grant($workspace, CreditGrantSource::Topup, 500);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 'run-812', 150);
    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credits");

    $response->assertOk()
        ->assertJsonPath('data.0.paid_by', [['source' => 'free', 'credits' => 100], ['source' => 'topup', 'credits' => 50]]);
});
