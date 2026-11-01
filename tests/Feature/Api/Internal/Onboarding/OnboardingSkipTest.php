<?php

use App\Enums\Onboarding\OnboardingStep;
use App\Models\User;

function onboardingSkipTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

function onboardingSkipUser(OnboardingStep $step): User
{
    return User::factory()->create([
        'password' => 'Password1!',
        'onboarding_current_step' => $step,
    ]);
}

it('moves past a skipped step and records it as skipped, not completed', function () {
    $user = onboardingSkipUser(OnboardingStep::ConnectApps);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $response = $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'connect_apps'])
        ->assertOk()
        ->assertJsonPath('message', 'Step skipped.')
        ->assertJsonPath('data.current_step', 'discovery_survey');

    $plan = collect($response->json('data.steps'))->firstWhere('key', 'connect_apps');

    expect($plan['skipped'])->toBeTrue()
        ->and($plan['completed'])->toBeFalse();
    expect($user->fresh()->onboarding_skipped_steps)->toBe(['connect_apps']);
});

it('does not send the user back to a skipped step at the next sign-in', function () {
    $user = onboardingSkipUser(OnboardingStep::ConnectApps);

    $first = onboardingSkipTokens($user->email, 'Password1!');
    $this->withToken($first['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'connect_apps'])
        ->assertOk();

    $second = onboardingSkipTokens($user->email, 'Password1!');
    $this->withToken($second['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'discovery_survey');
});

it('records a step once however many times it is skipped', function () {
    $user = onboardingSkipUser(OnboardingStep::ConnectApps);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    foreach (range(1, 2) as $attempt) {
        $this->withToken($tokens['access_token'])
            ->postJson('/api/v1/onboarding/skip', ['step' => 'connect_apps'])
            ->assertOk();
    }

    expect($user->fresh()->onboarding_skipped_steps)->toBe(['connect_apps']);
});

it('clears the skipped mark when the step is finished later', function () {
    $user = onboardingSkipUser(OnboardingStep::ConnectApps);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $client->postJson('/api/v1/onboarding/skip', ['step' => 'connect_apps'])->assertOk();

    $response = $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_apps'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'discovery_survey');

    $plan = collect($response->json('data.steps'))->firstWhere('key', 'connect_apps');

    expect($plan['skipped'])->toBeFalse()
        ->and($plan['completed'])->toBeTrue();
    expect($user->fresh()->onboarding_skipped_steps)->toBe([]);
});

it('refuses to skip the account setup steps', function (string $step) {
    $user = onboardingSkipUser(OnboardingStep::from($step));
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => $step])
        ->assertStatus(422);

    expect($user->fresh()->onboarding_skipped_steps)->toBeNull();
})->with(['profile_picture', 'create_workspace', 'choose_plan']);

it('rejects an unknown step', function () {
    $user = onboardingSkipUser(OnboardingStep::ConnectApps);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'not_a_step'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('step');
});

it('requires authentication to skip a step', function () {
    $this->postJson('/api/v1/onboarding/skip', ['step' => 'connect_apps'])
        ->assertUnauthorized();
});
