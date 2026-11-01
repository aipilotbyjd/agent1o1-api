<?php

use App\Models\Billing\Plan;
use App\Models\User;

function onboardingFlowTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('walks through all seven onboarding steps in order', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    // Step 0: profile_picture: no dedicated onboarding endpoint, avatar upload advances it implicitly via data only.
    $client->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'profile_picture');

    // Step 1: create_workspace
    $workspaceId = $client->postJson('/api/v1/workspaces', ['name' => 'Acme Inc'])
        ->assertCreated()
        ->json('data.workspace.id');

    $client->postJson('/api/v1/user/switch-workspace', ['workspace_id' => $workspaceId])
        ->assertOk();

    // Step 2: invite_team
    $client->postJson('/api/v1/onboarding/invite-team', [
        'emails' => ['teammate@example.com'],
        'role' => 'member',
    ])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'role_selection');

    // Step 3: role_selection
    $client->postJson('/api/v1/onboarding/role', ['job_role' => 'engineering'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_plan');

    // Step 4: choose_plan
    $client->postJson('/api/v1/onboarding/plan', ['plan_slug' => 'free'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_apps');

    // Step 5: connect_apps has no backend call; jump straight to discovery.

    // Step 6: discovery_survey
    $response = $client->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'google_search'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'discovery_survey');

    expect($response->json('data.completed'))->toBeFalse();

    $client->postJson('/api/v1/onboarding/complete')
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    $user->refresh();
    expect($user->onboarding_completed_at)->not->toBeNull();
});
