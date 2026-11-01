<?php

use App\Models\Billing\Subscription;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('leaves subscriptions with a future trial_ends_at untouched', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $subscription = Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_trial_future',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_test',
        'trial_ends_at' => now()->addDays(5),
    ]);

    $this->artisan('billing:expire-trials')
        ->expectsOutputToContain('Re-synced 0 stale trial subscription(s).')
        ->assertSuccessful();

    expect($subscription->fresh()->stripe_status)->toBe('trialing');
});

it('leaves already-active subscriptions untouched regardless of trial_ends_at', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_trial_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'trial_ends_at' => now()->subDays(2),
    ]);

    $this->artisan('billing:expire-trials')
        ->expectsOutputToContain('Re-synced 0 stale trial subscription(s).')
        ->assertSuccessful();
});
