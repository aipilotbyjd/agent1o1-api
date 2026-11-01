<?php

use App\Models\Billing\Plan;
use App\Models\User;

function onboardingTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('returns the onboarding state shape with meta', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'dismissed',
                'completed',
                'percent',
                'current_step',
                'steps' => [
                    '*' => ['key', 'label', 'description', 'completed'],
                ],
                'meta' => [
                    'workspace_slug_suggestion',
                    'plans',
                    'job_roles',
                    'discovery_sources',
                    'credential_types',
                ],
            ],
        ])
        ->assertJsonPath('data.current_step', 'profile_picture')
        ->assertJsonPath('data.percent', 0)
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.dismissed', false);
});

it('computes percent as completed steps accumulate', function () {
    Plan::factory()->create(['slug' => 'free', 'is_active' => true]);

    $user = User::factory()->create([
        'password' => 'Password1!',
        'avatar' => 'avatars/foo.png',
        'job_role' => 'engineering',
    ]);
    $tokens = onboardingTokens($user->email, 'Password1!');

    $response = $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk();

    $steps = collect($response->json('data.steps'));

    expect($steps->firstWhere('key', 'profile_picture')['completed'])->toBeTrue();
    expect($steps->firstWhere('key', 'role_selection')['completed'])->toBeTrue();
    expect($steps->firstWhere('key', 'create_workspace')['completed'])->toBeFalse();
});
