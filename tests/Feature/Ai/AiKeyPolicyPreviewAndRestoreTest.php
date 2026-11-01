<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-openai-key', 'ai.providers.anthropic.key' => 'platform-anthropic-key']);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}";
});

it('previews which models, agents and personal keys a stricter policy would affect', function () {
    $claude = ModelCatalog::factory()->create(['display_name' => 'Claude']);
    $claude->routes()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude', 'priority' => 0]);
    $gpt = ModelCatalog::factory()->create(['display_name' => 'GPT-4o']);
    $gpt->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($this->owner)->provider('anthropic')->create();
    $agent = Agent::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Recap bot', 'model_catalog_id' => $claude->id]);

    Passport::actingAs($this->owner);

    $impact = $this->postJson("{$this->base}/ai-key-policy/preview", ['platform_usage' => 'never', 'allow_personal_keys' => false])
        ->assertOk()
        ->json('data.impact');

    expect($impact['unavailable_models'])->toBe([['id' => $claude->id, 'display_name' => 'Claude']])
        ->and($impact['affected_agents'])->toBe([['id' => $agent->id, 'name' => 'Recap bot', 'model' => 'Claude']])
        ->and($impact['models_losing_backup'])->toBe([['id' => $gpt->id, 'display_name' => 'GPT-4o']])
        ->and($impact['ignored_personal_keys'])->toBe(1)
        ->and(WorkspaceAiKeyPolicy::query()->exists())->toBeFalse();
});

it('previews nothing for a policy that changes nothing', function () {
    Passport::actingAs($this->owner);

    expect($this->postJson("{$this->base}/ai-key-policy/preview", ['platform_usage' => 'fallback'])->json('data.impact'))
        ->toBe(['unavailable_models' => [], 'affected_agents' => [], 'models_losing_backup' => [], 'ignored_personal_keys' => 0]);
});

it('lets only admins preview a policy change', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    Passport::actingAs($member);

    $this->postJson("{$this->base}/ai-key-policy/preview", ['platform_usage' => 'never'])->assertForbidden();
});

it('restores a removed key, giving the default back only when its group has none', function () {
    $first = AiProviderCredential::factory()->forWorkspace($this->workspace)->default()->create(['created_at' => now()->subDay()]);
    $second = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    Passport::actingAs($this->owner);

    $this->deleteJson("{$this->base}/ai-provider-credentials/{$first->id}")->assertNoContent();
    expect($second->fresh()->is_default)->toBeTrue();

    $this->postJson("{$this->base}/ai-provider-credentials/{$first->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.ai_provider_credential.id', $first->id)
        ->assertJsonPath('data.ai_provider_credential.is_default', false);

    $this->deleteJson("{$this->base}/ai-provider-credentials/{$second->id}")->assertNoContent();
    $this->deleteJson("{$this->base}/ai-provider-credentials/{$first->id}")->assertNoContent();
    $this->postJson("{$this->base}/ai-provider-credentials/{$first->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.ai_provider_credential.is_default', true);

    expect(AuditLog::query()->where('action', 'ai_provider_credential.restored')->count())->toBe(2);
});

it('refuses to restore a key that was never removed, or someone else\'s personal key', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $live = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    $personal = AiProviderCredential::factory()->forWorkspace($this->workspace)->personal($member)->create();
    $personal->delete();

    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/ai-provider-credentials/{$live->id}/restore")->assertNotFound();
    $this->postJson("{$this->base}/ai-provider-credentials/{$personal->id}/restore")->assertNotFound();
});

it('describes each provider\'s key shape and where to find a key', function () {
    Passport::actingAs($this->owner);

    $anthropic = collect($this->getJson("{$this->base}/ai-providers")->json('data.providers'))->firstWhere('key', 'anthropic');

    expect($anthropic['key_prefix'])->toBe('sk-ant-')
        ->and($anthropic['key_guide'])->not->toBeEmpty();
});
