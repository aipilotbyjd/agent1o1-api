<?php

use App\Enums\Workspaces\Role;
use App\Exceptions\OwnAiKeyRequiredException;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-openai-key', 'ai.providers.anthropic.key' => 'platform-anthropic-key', 'ai.providers.openrouter.key' => null]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->registrar = app(ByokProviderRegistrar::class);
});

function setAiKeyPolicy(string $workspaceId, array $attributes): void
{
    WorkspaceAiKeyPolicy::query()->updateOrCreate(['workspace_id' => $workspaceId], $attributes);
}

it('keeps the platform key behind the workspace key by default', function () {
    $key = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    [$provider] = $this->registrar->apply(['openai' => 'gpt-4o', 'anthropic' => 'claude'], null, $this->workspace->id, null);

    expect($provider)->toBe(["byok-{$key->id}" => 'gpt-4o', 'openai' => 'gpt-4o', 'anthropic' => 'claude']);
});

it('drops every platform hop once the workspace has a key for the call when set to only-when-no-key', function () {
    setAiKeyPolicy($this->workspace->id, ['platform_usage' => 'when_no_key']);
    $key = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    [$covered] = $this->registrar->apply(['openai' => 'gpt-4o', 'anthropic' => 'claude'], null, $this->workspace->id, null);
    [$uncovered] = $this->registrar->apply(['anthropic' => 'claude'], null, $this->workspace->id, null);

    expect($covered)->toBe(["byok-{$key->id}" => 'gpt-4o'])
        ->and($uncovered)->toBe(['anthropic' => 'claude']);
});

it('never touches the platform keys when set to never, and refuses a model no workspace key covers', function () {
    setAiKeyPolicy($this->workspace->id, ['platform_usage' => 'never']);
    $key = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    [$covered] = $this->registrar->apply(['openai' => 'gpt-4o', 'anthropic' => 'claude'], null, $this->workspace->id, null);

    expect($covered)->toBe(["byok-{$key->id}" => 'gpt-4o'])
        ->and(fn () => $this->registrar->apply(['anthropic' => 'claude', 'xkiro' => 'm'], null, $this->workspace->id, null))
        ->toThrow(OwnAiKeyRequiredException::class, 'Add a key for Anthropic in Settings');
});

it('refuses knowledge-base embeddings without a workspace key when set to never', function () {
    setAiKeyPolicy($this->workspace->id, ['platform_usage' => 'never']);
    config(['ai.default_for_embeddings' => 'openai']);

    expect(fn () => $this->registrar->embeddingsProvider($this->workspace->id, null))->toThrow(OwnAiKeyRequiredException::class);
});

it('ignores personal keys when the workspace turns them off, without deleting them', function () {
    $member = User::factory()->create();
    $team = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    $personal = AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($member)->create();

    [$before] = $this->registrar->apply(['openai' => 'gpt-4o'], null, $this->workspace->id, $member->id);
    setAiKeyPolicy($this->workspace->id, ['allow_personal_keys' => false]);
    [$after] = $this->registrar->apply(['openai' => 'gpt-4o'], null, $this->workspace->id, $member->id);

    expect(array_key_first($before))->toBe("byok-{$personal->id}")
        ->and(array_key_first($after))->toBe("byok-{$team->id}")
        ->and(AiProviderCredential::find($personal->id))->not->toBeNull();
});

it('refuses a new personal key and flags kept ones when personal keys are off', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($member)->create();
    setAiKeyPolicy($this->workspace->id, ['allow_personal_keys' => false]);
    Http::fake();

    Passport::actingAs($member);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-provider-credentials", [
        'execution_provider' => 'openai', 'api_key' => 'sk-personal-123', 'scope' => 'personal',
    ])->assertUnprocessable()->assertJsonValidationErrors(['scope']);

    Http::assertNothingSent();
    expect($this->getJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers")->json('data.can_add_personal'))->toBeFalse()
        ->and($this->getJson("/api/v1/workspaces/{$this->workspace->id}/ai-provider-credentials")->json('data.ai_provider_credentials.0.ignored_by_policy'))->toBeTrue();
});

it('shows only models the workspace\'s own keys cover as available when set to never', function () {
    $platformOnly = ModelCatalog::factory()->create(['display_name' => 'A']);
    $platformOnly->routes()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude', 'priority' => 0]);
    $covered = ModelCatalog::factory()->create(['display_name' => 'B']);
    $covered->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    Passport::actingAs($this->owner);
    $url = "/api/v1/model-catalog?workspace_id={$this->workspace->id}";

    $before = collect($this->getJson($url)->json('data.model_catalog'))->pluck('is_available', 'id');
    setAiKeyPolicy($this->workspace->id, ['platform_usage' => 'never']);
    $after = collect($this->getJson($url)->json('data.model_catalog'))->pluck('is_available', 'id');

    expect($before[$platformOnly->id])->toBeTrue()
        ->and($after[$platformOnly->id])->toBeFalse()
        ->and($after[$covered->id])->toBeTrue();
});

it('lets anyone read the policy but only admins change it, and audits the change', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $url = "/api/v1/workspaces/{$this->workspace->id}/ai-key-policy";

    Passport::actingAs($member);
    $this->getJson($url)->assertOk()
        ->assertJsonPath('data.policy.platform_usage', 'fallback')
        ->assertJsonPath('data.policy.allow_personal_keys', true)
        ->assertJsonPath('data.policy.can_manage', false);
    $this->putJson($url, ['platform_usage' => 'never'])->assertForbidden();

    Passport::actingAs($this->owner);
    $this->putJson($url, ['platform_usage' => 'never', 'allow_personal_keys' => false])->assertOk()
        ->assertJsonPath('data.policy.platform_usage', 'never')
        ->assertJsonPath('data.policy.allow_personal_keys', false);
    $this->putJson($url, ['platform_usage' => 'sometimes'])->assertUnprocessable();

    expect(AuditLog::query()->where('action', 'ai_key_policy.updated')->exists())->toBeTrue();
});
