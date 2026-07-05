<?php

use App\Enums\DiscoverySource;
use App\Enums\JobRole;
use App\Enums\Role;
use App\Models\Credential;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('onboarding state reflects profile picture step', function () {
    $user = User::factory()->create(['avatar' => null]);

    $steps = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->json('data.steps');

    expect(collect($steps)->firstWhere('key', 'profile_picture')['completed'])->toBeFalse();
});

test('onboarding percent increases as steps complete', function () {
    $user = User::factory()->create([
        'avatar' => 'avatars/a.jpg',
        'job_role' => JobRole::Sales,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data');

    // 2 of 7 complete = ~29%
    expect($data['percent'])->toBe(29);
});

test('onboarding is fully completed when all 7 steps done', function () {
    $user = User::factory()->create([
        'avatar' => 'avatars/a.jpg',
        'job_role' => JobRole::Engineering,
        'discovery_source' => DiscoverySource::YouTube,
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    // Invite sent
    Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    // Subscription (free plan)
    $plan = Plan::where('slug', 'free')->first();
    $workspace->subscription()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'credits_per_cycle' => $plan->creditsMonthly(),
        'current_period_start' => now(),
        'current_period_end' => now()->addYear(),
    ]);

    // Credential connected
    Credential::factory()->create(['workspace_id' => $workspace->id]);

    $data = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data');

    expect($data['completed'])->toBeTrue()
        ->and($data['percent'])->toBe(100);
});

test('dismiss onboarding sets dismissed flag', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/user/dismiss-onboarding')
        ->assertOk();

    expect($response->json('data.onboarding_dismissed_at'))->not->toBeNull();
});
