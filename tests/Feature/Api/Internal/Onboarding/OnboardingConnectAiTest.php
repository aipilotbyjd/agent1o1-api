<?php

use App\Enums\Onboarding\OnboardingStep;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

function connectAiStep(User $user): array
{
    Passport::actingAs($user);

    return collect(test()->getJson('/api/v1/user/onboarding')->assertOk()->json('data.steps'))->firstWhere('key', 'connect_ai');
}

it('puts the connect AI step right after the role, before the template, and never lets it be skipped', function () {
    expect(OnboardingStep::RoleSelection->next())->toBe(OnboardingStep::ConnectAi)
        ->and(OnboardingStep::ConnectAi->next())->toBe(OnboardingStep::ChooseTemplate)
        ->and(OnboardingStep::ConnectAi->isSkippable())->toBeFalse();
});

it('refuses to skip the connect AI step', function () {
    $user = User::factory()->create(['onboarding_current_step' => OnboardingStep::ConnectAi]);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    Passport::actingAs($user);

    test()->postJson('/api/v1/onboarding/skip', ['step' => 'connect_ai'])->assertUnprocessable();

    expect($user->fresh()->onboarding_current_step)->toBe(OnboardingStep::ConnectAi);
});

it('marks the connect AI step done once the workspace has a key of its own', function () {
    $user = User::factory()->create(['onboarding_current_step' => OnboardingStep::ConnectAi]);
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $user->update(['current_workspace_id' => $workspace->id]);

    expect(connectAiStep($user)['completed'])->toBeFalse();

    AiProviderCredential::factory()->forWorkspace($workspace)->provider('openrouter')->create();

    expect(connectAiStep($user)['completed'])->toBeTrue();
});

it('does not count a key that stopped working', function () {
    $user = User::factory()->create(['onboarding_current_step' => OnboardingStep::ConnectAi]);
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $user->update(['current_workspace_id' => $workspace->id]);
    AiProviderCredential::factory()->forWorkspace($workspace)->invalid()->create();

    expect(connectAiStep($user)['completed'])->toBeFalse();
});

it('does not count a key that is not checked yet, or a personal key the workspace has turned off', function () {
    $user = User::factory()->create(['onboarding_current_step' => OnboardingStep::ConnectAi]);
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $user->update(['current_workspace_id' => $workspace->id]);
    AiProviderCredential::factory()->forWorkspace($workspace)->create(['validation_status' => 'unvalidated']);
    AiProviderCredential::factory()->forWorkspace($workspace)->personal($user)->create();
    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $workspace->id, 'allow_personal_keys' => false]);

    expect(connectAiStep($user)['completed'])->toBeFalse();
});
