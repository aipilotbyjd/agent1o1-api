<?php

use App\Models\Ai\AiProviderCredential;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;

function onboardingFlowTokens(string $email, string $password): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->json('data.tokens');
}

it('walks through the onboarding steps in order', function () {
    $user = User::factory()->create(['password' => 'Password1!', 'email' => 'jane@northwind.io']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);

    // Opening the wizard sets up the workspace: no step asks for one.
    $client->postJson('/api/v1/onboarding/start')
        ->assertOk()
        ->assertJsonPath('data.current_step', 'role_selection');

    $workspace = $user->fresh()->currentWorkspace;
    expect($workspace->name)->toBe('Northwind');

    $client->postJson('/api/v1/onboarding/role', ['job_role' => 'sales'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_ai');

    // There is no platform model: the AI step waits for a key of the workspace's own.
    $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_ai'])->assertUnprocessable();

    AiProviderCredential::factory()->forWorkspace($workspace)->provider('openrouter')->create();

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'connect_ai'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'choose_template');

    $client->postJson('/api/v1/onboarding/continue', ['step' => 'choose_template'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'first_run');

    $workflow = Workflow::factory()->for($workspace)->create();
    Run::factory()->forWorkflow($workflow)->completed()->create([
        'triggered_by' => $user->id,
        'trigger_type' => 'sample',
    ]);

    $state = $client->getJson('/api/v1/user/onboarding')->assertOk();
    expect(collect($state->json('data.steps'))->firstWhere('key', 'first_run')['completed'])->toBeTrue()
        ->and($state->json('data.completed'))->toBeFalse();

    $client->postJson('/api/v1/onboarding/complete')
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('saves the discovery answer without moving onboarding progress', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');

    $this->withToken($tokens['access_token'])
        ->postJson('/api/v1/onboarding/discovery', ['discovery_source' => 'google_search'])
        ->assertOk()
        ->assertJsonPath('data.discovery_answered', true)
        ->assertJsonPath('data.current_step', 'role_selection');

    expect($user->fresh()->discovery_source?->value)->toBe('google_search');
});

it('does not carry progress past the AI step while the workspace has no key', function () {
    $user = User::factory()->create(['password' => 'Password1!']);
    $tokens = onboardingFlowTokens($user->email, 'Password1!');
    $client = $this->withToken($tokens['access_token']);
    $client->postJson('/api/v1/onboarding/start')->assertOk();

    $client->postJson('/api/v1/onboarding/skip', ['step' => 'first_run'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 'connect_ai');
});
