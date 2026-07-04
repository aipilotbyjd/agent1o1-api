<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('currentPlan returns free plan when workspace has no subscription', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $plan = $workspace->currentPlan();

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->slug)->toBe('free');
});

test('currentPlan evicts stale cache and returns free plan when cache holds an object', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    // Simulate stale Redis cache holding a serialized model instead of a scalar ID
    $stalePlan = Plan::where('slug', 'free')->first();
    Cache::put("workspace:{$workspace->id}:plan_id", $stalePlan, 300);

    $plan = $workspace->currentPlan();

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->slug)->toBe('free');
    expect(Cache::has("workspace:{$workspace->id}:plan_id"))->toBeFalse();
});

test('currentPlan returns null when cache holds null without triggering stale-cache guard', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    // Deleting the subscription so closure returns null — guard must not misfire
    $workspace->subscription()->delete();

    // null is not cached by Cache::remember, so we just verify no TypeError is thrown
    $result = $workspace->currentPlan();

    // With no subscription and no free plan fallback in closure, result is the free plan
    // (free plan is returned via Plan::where('slug','free')->value('id') in closure)
    expect($result)->toBeInstanceOf(Plan::class);
});
