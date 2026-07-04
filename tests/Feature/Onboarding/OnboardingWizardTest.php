<?php

use App\Enums\DiscoverySource;
use App\Enums\JobRole;
use App\Enums\OnboardingStep;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

// ── GET /api/v1/user/onboarding ───────────────────────────────────────────────

test('onboarding state returns 7 wizard steps with meta', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->assertOk();

    $data = $response->json('data');

    expect($data['steps'])->toHaveCount(7)
        ->and($data['meta'])->toHaveKeys(['plans', 'credential_types', 'job_roles', 'discovery_sources', 'workspace_slug_suggestion'])
        ->and($data['current_step'])->toBe(OnboardingStep::ProfilePicture->value)
        ->and($data['percent'])->toBe(0);
});

test('profile picture step is complete when avatar is set', function () {
    $user = User::factory()->create(['avatar' => 'avatars/test.jpg']);

    $steps = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data.steps');

    $step = collect($steps)->firstWhere('key', 'profile_picture');
    expect($step['completed'])->toBeTrue();
});

test('create workspace step is complete when user has a workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $steps = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data.steps');

    expect(collect($steps)->firstWhere('key', 'create_workspace')['completed'])->toBeTrue();
});

test('role selection step is complete when job_role is set', function () {
    $user = User::factory()->create(['job_role' => JobRole::Engineering]);

    $steps = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data.steps');

    expect(collect($steps)->firstWhere('key', 'role_selection')['completed'])->toBeTrue();
});

test('discovery step is complete when discovery_source is set', function () {
    $user = User::factory()->create(['discovery_source' => DiscoverySource::YouTube]);

    $steps = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data.steps');

    expect(collect($steps)->firstWhere('key', 'discovery_survey')['completed'])->toBeTrue();
});

test('meta includes plans from database', function () {
    $user = User::factory()->create();

    $meta = $this->actingAs($user, 'api')
        ->getJson('/api/v1/user/onboarding')
        ->json('data.meta');

    expect($meta['plans'])->not->toBeEmpty()
        ->and($meta['plans'][0])->toHaveKeys(['id', 'name', 'slug', 'price_monthly', 'features']);
});

// ── POST /api/v1/onboarding/role ─────────────────────────────────────────────

test('save role updates user job_role and returns updated state', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/role', ['job_role' => 'engineering'])
        ->assertOk();

    expect($user->fresh()->job_role)->toBe(JobRole::Engineering)
        ->and(collect($response->json('data.steps'))->firstWhere('key', 'role_selection')['completed'])->toBeTrue();
});

test('save role rejects invalid job_role', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/role', ['job_role' => 'invalid_role'])
        ->assertUnprocessable();
});

// ── POST /api/v1/onboarding/discovery ────────────────────────────────────────

test('save discovery updates user discovery_source and returns updated state', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'youtube'])
        ->assertOk();

    expect($user->fresh()->discovery_source)->toBe(DiscoverySource::YouTube)
        ->and(collect($response->json('data.steps'))->firstWhere('key', 'discovery_survey')['completed'])->toBeTrue();
});

test('save discovery rejects invalid source', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'fax_machine'])
        ->assertUnprocessable();
});

// ── POST /api/v1/onboarding/invite-team ──────────────────────────────────────

test('invite team sends bulk invitations and returns updated state', function () {
    Notification::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/invite-team', [
            'emails' => ['alice@example.com', 'bob@example.com'],
            'role' => 'member',
            'personal_note' => 'Excited to build together!',
        ])
        ->assertOk();

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, 2);

    expect(collect($response->json('data.steps'))->firstWhere('key', 'invite_team')['completed'])->toBeTrue();
});

test('invite team requires an active workspace', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/invite-team', [
            'emails' => ['alice@example.com'],
            'role' => 'member',
        ])
        ->assertUnprocessable();
});

test('invite team skips duplicate emails silently', function () {
    Notification::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    // First invite
    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/invite-team', [
            'emails' => ['alice@example.com'],
            'role' => 'member',
        ])
        ->assertOk();

    // Same email again — should not throw
    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/invite-team', [
            'emails' => ['alice@example.com'],
            'role' => 'member',
        ])
        ->assertOk();

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, 1);
});

// ── POST /api/v1/onboarding/plan ─────────────────────────────────────────────

test('selecting free plan creates subscription and marks step complete', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/plan', ['plan_slug' => 'free'])
        ->assertOk();

    expect(collect($response->json('data.steps'))->firstWhere('key', 'choose_plan')['completed'])->toBeTrue();
});

// ── POST /api/v1/onboarding/complete ─────────────────────────────────────────

test('complete sets onboarding_dismissed_at on user', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/complete')
        ->assertOk();

    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();
});

test('complete is idempotent', function () {
    $user = User::factory()->create(['onboarding_dismissed_at' => now()->subDay()]);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/onboarding/complete')
        ->assertOk();

    expect($user->fresh()->onboarding_dismissed_at->isYesterday())->toBeTrue();
});
