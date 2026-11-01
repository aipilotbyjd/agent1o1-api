<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Workspaces\Role;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function byokWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

function byokMember(Workspace $workspace, Role $role): User
{
    $user = User::factory()->create();
    $workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    return $user;
}

function byokUrl(Workspace $workspace, string $path = ''): string
{
    return "/api/v1/workspaces/{$workspace->id}/ai-provider-credentials{$path}";
}

it('lists the supported providers with the catalog models each key would run', function () {
    [$workspace, $owner] = byokWorkspace();
    $catalog = ModelCatalog::factory()->create(['display_name' => 'GPT-4o']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o']);
    $internal = ModelCatalog::factory()->create(['display_name' => 'Hidden', 'is_internal' => true]);
    ModelRoute::factory()->forCatalog($internal)->create(['execution_provider' => 'openai', 'execution_model_id' => 'x']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/ai-providers")->assertOk();

    $openai = collect($response->json('data.providers'))->firstWhere('key', 'openai');
    expect($openai['label'])->toBe('OpenAI')
        ->and($openai['models'])->toBe(['GPT-4o'])
        ->and($openai['covers_knowledge_base'])->toBe(config('ai.default_for_embeddings') === 'openai')
        ->and(collect($response->json('data.providers'))->pluck('key'))->not->toContain('xkiro')
        ->and($response->json('data.can_add_team'))->toBeTrue()
        ->and($response->json('data.can_add_personal'))->toBeTrue();
});

it('checks and stores a key, never exposing it, and makes the first key the default', function () {
    Http::fake(['api.openai.com/*' => Http::response(['data' => []])]);
    [$workspace, $owner] = byokWorkspace();

    Passport::actingAs($owner);

    $response = $this->postJson(byokUrl($workspace), [
        'execution_provider' => 'openai',
        'api_key' => 'sk-live-supersecretvalue1234',
        'name' => 'Team OpenAI',
    ])->assertCreated();

    $response->assertJsonMissing(['sk-live-supersecretvalue1234']);
    expect($response->json('data.ai_provider_credential'))
        ->toMatchArray([
            'execution_provider' => 'openai',
            'scope' => 'team',
            'is_default' => true,
            'validation_status' => 'valid',
            'key_hint' => 'sk-l…1234',
            'can_manage' => true,
        ])
        ->not->toHaveKey('data');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/models'
        && $request->hasHeader('Authorization', 'Bearer sk-live-supersecretvalue1234'));

    expect(AiProviderCredential::sole()->apiKey())->toBe('sk-live-supersecretvalue1234');
});

it('refuses a key the provider rejects and stores nothing', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['type' => 'authentication_error']], 401)]);
    [$workspace, $owner] = byokWorkspace();

    Passport::actingAs($owner);

    $this->postJson(byokUrl($workspace), ['execution_provider' => 'anthropic', 'api_key' => 'sk-ant-wrong-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['api_key']);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-wrong-key'));
    expect(AiProviderCredential::count())->toBe(0);
});

it('stores a key unvalidated when the provider cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));
    [$workspace, $owner] = byokWorkspace();

    Passport::actingAs($owner);

    $this->postJson(byokUrl($workspace), ['execution_provider' => 'openai', 'api_key' => 'sk-maybe-fine-key'])
        ->assertCreated()
        ->assertJsonPath('data.ai_provider_credential.validation_status', 'unvalidated');
});

it('rejects an unsupported provider', function () {
    [$workspace, $owner] = byokWorkspace();

    Passport::actingAs($owner);

    $this->postJson(byokUrl($workspace), ['execution_provider' => 'xkiro', 'api_key' => 'sk-whatever-123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['execution_provider']);
});

it('lets a member add a personal key but not a team key', function () {
    Http::fake(['api.openai.com/*' => Http::response(['data' => []])]);
    [$workspace] = byokWorkspace();
    $member = byokMember($workspace, Role::Member);

    Passport::actingAs($member);

    $this->postJson(byokUrl($workspace), ['execution_provider' => 'openai', 'api_key' => 'sk-team-attempt-1'])->assertForbidden();
    $this->postJson(byokUrl($workspace), ['execution_provider' => 'openai', 'api_key' => 'sk-personal-key-1', 'scope' => 'personal'])
        ->assertCreated()
        ->assertJsonPath('data.ai_provider_credential.scope', 'personal');
});

it('does not let a viewer add any key', function () {
    [$workspace] = byokWorkspace();
    $viewer = byokMember($workspace, Role::Viewer);

    Passport::actingAs($viewer);

    $this->postJson(byokUrl($workspace), ['execution_provider' => 'openai', 'api_key' => 'sk-personal-key-1', 'scope' => 'personal'])->assertForbidden();
});

it('hides a personal key from every other member, owners included', function () {
    [$workspace, $owner] = byokWorkspace();
    $member = byokMember($workspace, Role::Member);
    $personal = AiProviderCredential::factory()->forWorkspace($workspace)->personal($member)->create();
    $team = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    expect(collect($this->getJson(byokUrl($workspace))->assertOk()->json('data.ai_provider_credentials'))->pluck('id')->all())
        ->toBe([$team->id]);
    $this->deleteJson(byokUrl($workspace, "/{$personal->id}"))->assertNotFound();

    Passport::actingAs($member);

    $listed = collect($this->getJson(byokUrl($workspace))->assertOk()->json('data.ai_provider_credentials'));
    expect($listed->pluck('id')->sort()->values()->all())->toBe(collect([$personal->id, $team->id])->sort()->values()->all())
        ->and($listed->firstWhere('id', $team->id)['can_manage'])->toBeFalse()
        ->and($listed->firstWhere('id', $personal->id)['can_manage'])->toBeTrue();
    $this->deleteJson(byokUrl($workspace, "/{$team->id}"))->assertForbidden();
});

it('re-checks a replaced key and refuses one the provider rejects', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['error' => 'bad'], 401)
            ->push(['data' => []]),
    ]);
    [$workspace, $owner] = byokWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->invalid()->create(['data' => ['api_key' => 'sk-old-key-0000']]);

    Passport::actingAs($owner);

    $this->patchJson(byokUrl($workspace, "/{$credential->id}"), ['api_key' => 'sk-also-wrong-1'])->assertUnprocessable();
    expect($credential->fresh()->apiKey())->toBe('sk-old-key-0000');

    $this->patchJson(byokUrl($workspace, "/{$credential->id}"), ['api_key' => 'sk-new-good-key-9999', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.ai_provider_credential.validation_status', 'valid')
        ->assertJsonPath('data.ai_provider_credential.name', 'Renamed')
        ->assertJsonPath('data.ai_provider_credential.key_hint', 'sk-n…9999');
});

it('hands the default to the next key when the default is removed', function () {
    [$workspace, $owner] = byokWorkspace();
    $first = AiProviderCredential::factory()->forWorkspace($workspace)->default()->create(['created_at' => now()->subDay()]);
    $second = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson(byokUrl($workspace, "/{$first->id}"))->assertNoContent();

    expect($second->fresh()->is_default)->toBeTrue();
});

it('switches the default within a group', function () {
    [$workspace, $owner] = byokWorkspace();
    $first = AiProviderCredential::factory()->forWorkspace($workspace)->default()->create();
    $second = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson(byokUrl($workspace, "/{$second->id}/default"))->assertOk()->assertJsonPath('data.ai_provider_credential.is_default', true);

    expect($first->fresh()->is_default)->toBeFalse();
});

it('re-checks a stored key on demand and records a rejection', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'revoked'], 401)]);
    [$workspace, $owner] = byokWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->postJson(byokUrl($workspace, "/{$credential->id}/validate"))
        ->assertOk()
        ->assertJsonPath('data.result.ok', false)
        ->assertJsonPath('data.ai_provider_credential.validation_status', 'invalid');

    expect($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Invalid);
});

it('does not leak another workspace key', function () {
    [$workspace, $owner] = byokWorkspace();
    [$otherWorkspace] = byokWorkspace();
    $foreign = AiProviderCredential::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->patchJson(byokUrl($workspace, "/{$foreign->id}"), ['name' => 'x'])->assertNotFound();
    $this->deleteJson(byokUrl($workspace, "/{$foreign->id}"))->assertNotFound();
});
