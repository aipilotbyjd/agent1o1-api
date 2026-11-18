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
    $user = onboardingSkipUser(OnboardingStep::ChooseTemplate);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $response = $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])
        ->assertOk()
        ->assertJsonPath('message', 'Step skipped.')
        ->assertJsonPath('data.current_step', 'first_run');

    $template = collect($response->json('data.steps'))->firstWhere('key', 'choose_template');

    expect($template['skipped'])->toBeTrue()
        ->and($template['completed'])->toBeFalse();
    expect($user->fresh()->onboarding_skipped_steps)->toBe(['choose_template']);
});

it('does not send the user back to a skipped step at the next sign-in', function () {
    $user = onboardingSkipUser(OnboardingStep::ChooseTemplate);

    $first = onboardingSkipTokens($user->email, 'Password1!');
    $this->withToken($first['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])
        ->assertOk();

    $second = onboardingSkipTokens($user->email, 'Password1!');
    $this->withToken($second['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'first_run');
});

it('records a step once however many times it is skipped', function () {
    $user = onboardingSkipUser(OnboardingStep::ChooseTemplate);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    foreach (range(1, 2) as $attempt) {
        $this->withToken($tokens['access_token'])
            ->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])
            ->assertOk();
    }

    expect($user->fresh()->onboarding_skipped_steps)->toBe(['choose_template']);
});

it('clears the skipped mark when the step is finished later', function () {
    $user = onboardingSkipUser(OnboardingStep::ChooseTemplate);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    $client->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])->assertOk();

    $response = $client->postJson('/api/v1/onboarding/continue', ['step' => 'choose_template'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'first_run');

    $template = collect($response->json('data.steps'))->firstWhere('key', 'choose_template');

    expect($template['skipped'])->toBeFalse()
        ->and($template['completed'])->toBeTrue();
    expect($user->fresh()->onboarding_skipped_steps)->toBe([]);
});

it('lets the first run be postponed', function () {
    $user = onboardingSkipUser(OnboardingStep::FirstRun);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $response = $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'first_run'])
        ->assertOk();

    $run = collect($response->json('data.steps'))->firstWhere('key', 'first_run');

    expect($run['skipped'])->toBeTrue()
        ->and($run['completed'])->toBeFalse();
});

it('rejects an unknown step', function () {
    $user = onboardingSkipUser(OnboardingStep::ChooseTemplate);
    $tokens = onboardingSkipTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/skip', ['step' => 'not_a_step'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('step');
});

it('requires authentication to skip a step', function () {
    $this->postJson('/api/v1/onboarding/skip', ['step' => 'choose_template'])
        ->assertUnauthorized();
});
