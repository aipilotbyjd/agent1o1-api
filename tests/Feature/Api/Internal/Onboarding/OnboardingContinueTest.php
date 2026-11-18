<?php

use App\Enums\Onboarding\OnboardingStep;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

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

it('saves a continued step so the next sign-in resumes after it', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::RoleSelection]);

    $first = onboardingContinueTokens($user->email, 'Password1!');
    $this->withToken($first['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'role_selection'])
        ->assertOk()
        ->assertJsonPath('message', 'Progress saved.')
        ->assertJsonPath('data.current_step', 'connect_ai');

    $second = onboardingContinueTokens($user->email, 'Password1!');
    $this->withToken($second['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_ai');
});

it('no longer has the workspace, plan, apps or invite steps', function (string $step) {
    $user = onboardingContinueUser();
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => $step])
        ->assertStatus(422)
        ->assertJsonValidationErrors('step');
})->with(['create_workspace', 'choose_plan', 'connect_apps', 'invite_team']);

it('does not let the AI step continue until the workspace has a working key', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::ConnectAi]);
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_ai'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Connect an AI provider before continuing.');

    AiProviderCredential::factory()->forWorkspace($workspace)->provider('openrouter')->create();

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_ai'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_template');
});

it('never moves progress backwards', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::FirstRun]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'role_selection'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'first_run');
});

it('clears a skipped mark when the step is continued later', function () {
    $user = onboardingContinueUser([
        'onboarding_current_step' => OnboardingStep::FirstRun,
        'onboarding_skipped_steps' => ['choose_template'],
    ]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'choose_template'])
        ->assertOk();

    expect($user->fresh()->onboarding_skipped_steps)->toBe([]);
});

it('saves the template step without requiring a template to be used', function () {
    $user = onboardingContinueUser(['onboarding_current_step' => OnboardingStep::ChooseTemplate]);
    $tokens = onboardingContinueTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/continue', ['step' => 'choose_template'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'first_run');
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
    $this->postJson('/api/v1/onboarding/continue', ['step' => 'choose_template'])->assertUnauthorized();
});
