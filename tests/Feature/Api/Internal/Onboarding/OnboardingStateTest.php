<?php

use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function onboardingTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('returns the onboarding state shape with meta', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'dismissed',
                'discovery_answered',
                'completed',
                'percent',
                'current_step',
                'steps' => [
                    '*' => ['key', 'label', 'description', 'skippable', 'completed', 'skipped'],
                ],
                'meta' => [
                    'job_roles',
                    'discovery_sources',
                    'recommended_templates',
                ],
            ],
        ])
        ->assertJsonPath('data.current_step', 'role_selection')
        ->assertJsonPath('data.steps.*.key', ['role_selection', 'connect_ai', 'choose_template', 'first_run'])
        ->assertJsonPath('data.percent', 0)
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.dismissed', false);
});

it('computes percent as completed steps accumulate', function () {
    $user = User::factory()->create([
        'password' => 'Password1!',
        'job_role' => 'engineering',
    ]);
    $tokens = onboardingTokens($user->email, 'Password1!');

    $response = $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk();

    $steps = collect($response->json('data.steps'));

    expect($steps->firstWhere('key', 'role_selection')['completed'])->toBeTrue()
        ->and($steps->firstWhere('key', 'connect_ai')['completed'])->toBeFalse()
        ->and($response->json('data.percent'))->toBe(25);
});

it('offers the template a signup link pointed at first', function () {
    WorkflowTemplate::factory()->global()->count(3)->create(['is_featured' => true]);
    $linked = WorkflowTemplate::factory()->global()->create(['slug' => 'sales-call-prep-brief']);

    $user = User::factory()->create([
        'password' => 'Password1!',
        'signup_attribution' => ['template' => 'sales-call-prep-brief'],
    ]);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $tokens = onboardingTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->getJson('/api/v1/user/onboarding')
        ->assertOk()
        ->assertJsonPath('data.meta.recommended_templates.0.id', $linked->id);
});
