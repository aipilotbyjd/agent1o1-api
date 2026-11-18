<?php

use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('sets up a workspace named after the company in a work email', function () {
    $user = User::factory()->create(['email' => 'jane@acme-robotics.io']);
    Passport::actingAs($user);

    $this->postJson('/api/v1/onboarding/start')->assertOk();

    $workspace = $user->fresh()->currentWorkspace;

    expect($workspace->name)->toBe('Acme Robotics')
        ->and($workspace->owner_id)->toBe($user->id);
});

it('names the workspace after the person for a shared mail provider', function () {
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.doe@gmail.com']);
    Passport::actingAs($user);

    $this->postJson('/api/v1/onboarding/start')->assertOk();

    expect($user->fresh()->currentWorkspace->name)->toBe("Jane's workspace");
});

it('sets up only one workspace however often the wizard opens', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->postJson('/api/v1/onboarding/start')->assertOk();
    $this->postJson('/api/v1/onboarding/start')->assertOk();

    expect($user->workspaces()->count())->toBe(1);
});

it('does not give an invited member a workspace of their own', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->postJson('/api/v1/onboarding/start')->assertOk();

    expect($member->workspaces()->count())->toBe(1)
        ->and($member->ownedWorkspaces()->count())->toBe(0);
});

it('requires authentication to start onboarding', function () {
    $this->postJson('/api/v1/onboarding/start')->assertUnauthorized();
});
