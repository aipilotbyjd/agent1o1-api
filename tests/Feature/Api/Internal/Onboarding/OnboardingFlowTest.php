<?php

use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function onboardingFlowTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('walks through the onboarding steps in order', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $client->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'create_workspace');

    // create_workspace
    $workspaceId = $client->postJson('/api/v1/workspaces', ['name' => 'Acme Inc'])
        ->assertCreated()
        ->json('data.workspace.id');

    $client->postJson('/api/v1/user/switch-workspace', ['workspace_id' => $workspaceId])
        ->assertOk();

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'create_workspace'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'role_selection');

    // role_selection
    $client->postJson('/api/v1/onboarding/role', ['job_role' => 'engineering'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_template');

    // choose_template is optional.
    $client->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_plan');

    // choose_plan
    $client->postJson('/api/v1/onboarding/plan', ['plan_slug' => 'free'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_ai');

    // connect_ai is skippable; the member starts on the free model.
    $client->postJson('/api/v1/onboarding/skip', ['step' => 'connect_ai'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_apps');

    // connect_apps collects nothing of its own.
    $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_apps'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'invite_team');

    // invite_team
    $client->postJson('/api/v1/onboarding/invite-team', [
        'emails' => ['teammate@example.com'],
        'role' => 'member',
    ])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'discovery_survey');

    // discovery_survey leaves only the optional profile picture.
    $response = $client->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'google_search'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'profile_picture');

    expect($response->json('data.completed'))->toBeFalse();

    $client->postJson('/api/v1/onboarding/complete')
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    $user->refresh();
    expect($user->onboarding_completed_at)->not->toBeNull();
});

it('does not let a later step carry progress past an open workspace step', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'google_search'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'create_workspace');
});

it('does not count the plan as chosen because a later step was finished first', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $response = $client->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'google_search'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_plan');

    expect(collect($response->json('data.steps'))->firstWhere('key', 'choose_plan')['completed'])->toBeFalse();

    $client->postJson('/api/v1/onboarding/complete')->assertUnprocessable();
    $client->postJson('/api/v1/user/dismiss-onboarding')->assertUnprocessable();

    expect($user->fresh()->onboarding_completed_at)->toBeNull()
        ->and($user->fresh()->onboarding_dismissed_at)->toBeNull();
});
