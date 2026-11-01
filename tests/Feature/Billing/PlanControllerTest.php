<?php

use App\Enums\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function billingLoginTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('lists only active plans ordered by sort_order', function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'inactive', 'is_active' => false, 'sort_order' => 0]);
    Plan::factory()->create(['slug' => 'second', 'is_active' => true, 'sort_order' => 2]);
    Plan::factory()->create(['slug' => 'first', 'is_active' => true, 'sort_order' => 1]);

    $owner = User::factory()->create(['password' => 'Password1!']);
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $tokens = billingLoginTokens($owner->email, 'Password1!');

    $response = test()->withToken($tokens['access_token'])
        ->getJson("/api/v1/workspaces/{$workspace->id}/billing/plans")
        ->assertOk();

    $slugs = collect($response->json('data.plans'))->pluck('slug');

    expect($slugs->all())->toBe(['first', 'second']);
});

it('unlocks credit packs on every seeded paid plan', function () {
    Plan::query()->delete();
    test()->syncSetup('plans');

    $paid = Plan::query()->where('price_monthly', '>', 0)->get();

    expect($paid)->not->toBeEmpty();

    // The feature key existed on the enum but on no seeded plan, so pack
    // checkout threw FeatureNotAvailableException for every real customer.
    $paid->each(fn (Plan $plan) => expect($plan->hasFeature(Feature::CreditPacks))->toBeTrue());
});

it('leaves credit packs locked on the free plan', function () {
    Plan::query()->delete();
    test()->syncSetup('plans');

    expect(Plan::query()->where('slug', 'free')->sole()->hasFeature(Feature::CreditPacks))->toBeFalse();
});

it('seeds a default plan that config(billing.default_plan) resolves', function () {
    Plan::query()->delete();
    test()->syncSetup('plans');

    expect(Plan::default())->not->toBeNull();
    expect(Plan::default()->slug)->toBe(config('billing.default_plan'));
});
