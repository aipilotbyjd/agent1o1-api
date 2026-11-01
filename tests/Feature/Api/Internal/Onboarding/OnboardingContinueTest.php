<?php

use App\Enums\Onboarding\OnboardingStep;
use App\Models\User;

function onboardingContinueTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

function onboardingContinueUser(array $attributes = []): User
{
    return User::factory()->create(['password' => 'Password1!', ...$attributes]);
}

it('saves the profile picture step so the next sign-in resumes at the workspace step', function () {
    $user = onboardingContinueUser(['avatar' => 'avatars/logo.png']);

    $first = onboardingContinueTokens($user->email, 'Password1!');
    $this->withToken($first['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'profile_picture'])
        ->assertOk()
        ->assertJsonPath('message', 'Progress saved.')
        ->assertJsonPath('data.current_step', 'create_workspace');

    $second = onboardingContinueTokens($user->email, 'Password1!');
    $this->withToken($second['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'create_workspace');
});

it('does not let the profile picture step continue without a picture', function () {
    $user = onboardingContinueUser();
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'profile_picture'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Add a profile picture before continuing.');

    expect($user->fresh()->onboarding_current_step)->toBe(OnboardingStep::ProfilePicture);
});

it('does not let the workspace step continue without a workspace', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::CreateWorkspace]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'create_workspace'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Create a workspace before continuing.');
});

it('saves the workspace step once a workspace exists', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::CreateWorkspace]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $workspaceId = $client->postJson('/api/v1/workspaces', ['name' => 'Acme Inc'])
        ->assertCreated()
        ->json('data.workspace.id');

    $client->postJson('/api/v1/user/switch-workspace', ['workspace_id' => $workspaceId])->assertOk();

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'create_workspace'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'invite_team');
});

it('saves a step that collects nothing, such as connect apps', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::ConnectApps]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'connect_apps'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'discovery_survey');
});

it('never moves progress backwards', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::ChoosePlan, 'avatar' => 'avatars/logo.png']);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'profile_picture'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_plan');
});

it('clears a skipped mark when the step is continued later', function () {
    $user = onboardingContinueUser([
        'onboarding_current_step' => OnboardingStep::DiscoverySurvey,
        'onboarding_skipped_steps' => ['connect_apps'],
    ]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'connect_apps'])
        ->assertOk();

    expect($user->fresh()->onboarding_skipped_steps)->toBe([]);
});

it('rejects an unknown step', function () {
    $user = onboardingContinueUser();
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'not_a_step'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('step');
});

it('requires authentication to continue a step', function () {
    $this->postJson('/api/v1/onboarding/continue', ['step' => 'connect_apps'])->assertUnauthorized();
});

it('does not let the plan step continue before a plan is chosen', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::ChoosePlan]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'choose_plan'])
        ->assertUnprocessable();

    expect($user->fresh()->onboarding_current_step)->toBe(OnboardingStep::ChoosePlan);
});
