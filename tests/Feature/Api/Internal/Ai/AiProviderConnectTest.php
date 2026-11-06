<?php

use App\Enums\Ai\AiProviderAuthMethod;
use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Role;
use App\Models\Ai\AiProviderConnectState;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\Connect\AiProviderConnectFlow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'app.frontend_url' => 'https://app.example.test',
        'byok.connect.redirect_path' => '/oauth/ai-provider-complete',
        'byok.providers.openrouter.connect.enabled' => true,
        'byok.providers.huggingface.connect.enabled' => true,
        'byok.providers.huggingface.connect.client_id' => 'hf-client-id',
        'byok.providers.huggingface.connect.client_secret' => null,
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

function connectMember(Workspace $workspace, Role $role): User
{
    $user = User::factory()->create();
    $workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    return $user;
}

/**
 * @return array{authorize_url: string, scope: string, query: array<string, string>}
 */
function startConnect(Workspace $workspace, string $provider): array
{
    $data = test()->postJson("/api/v1/workspaces/{$workspace->id}/ai-providers/{$provider}/connect")
        ->assertOk()
        ->json('data');

    parse_str((string) parse_url($data['authorize_url'], PHP_URL_QUERY), $query);

    return [...$data, 'query' => $query];
}

function completeConnect(string $state, string $code = 'auth-code')
{
    return test()->postJson('/api/v1/ai-providers/connect/complete', ['state' => $state, 'code' => $code]);
}

it('lists which providers offer one-click connect and a free tier', function () {
    config(['byok.providers.huggingface.connect.client_id' => null]);
    Passport::actingAs($this->owner);

    $providers = collect($this->getJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers")->assertOk()->json('data.providers'))->keyBy('key');

    expect($providers['openrouter']['connect'])->toBe(['label' => 'Connect with OpenRouter'])
        ->and($providers['openrouter']['free_tier'])->not->toBeNull()
        ->and($providers['gemini']['free_tier'])->not->toBeNull()
        ->and($providers['anthropic']['connect'])->toBeNull()
        ->and($providers['anthropic']['free_tier'])->toBeNull()
        // No client id registered: Hugging Face can only be pasted.
        ->and($providers['huggingface']['connect'])->toBeNull()
        // Sign in with ChatGPT stays off until OpenAI approves the app.
        ->and($providers['openai']['connect'])->toBeNull();
});

it('sends an admin to OpenRouter with a PKCE challenge and adds a team key on return', function () {
    Http::fake([
        'openrouter.ai/api/v1/auth/keys' => Http::response(['key' => 'sk-or-v1-connectedkey1234', 'user_id' => 'user_1']),
        'openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'Agent 101']]),
    ]);
    Passport::actingAs($this->owner);

    $started = startConnect($this->workspace, 'openrouter');

    expect($started['scope'])->toBe('team')
        ->and($started['authorize_url'])->toStartWith('https://openrouter.ai/auth?')
        ->and($started['query'])->toMatchArray([
            'callback_url' => 'https://app.example.test/oauth/ai-provider-complete',
            'code_challenge_method' => 'S256',
        ]);

    $pending = AiProviderConnectState::sole();
    expect($started['query']['state'])->toBe($pending->state)
        ->and($started['query']['code_challenge'])->toBe(AiProviderConnectFlow::codeChallenge($pending->code_verifier));

    $response = completeConnect($pending->state, 'or-code')->assertCreated();

    expect($response->json('data.ai_provider_credential'))->toMatchArray([
        'execution_provider' => 'openrouter',
        'scope' => 'team',
        'auth_method' => 'api_key',
        'validation_status' => 'valid',
    ]);
    $response->assertJsonMissing(['sk-or-v1-connectedkey1234']);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://openrouter.ai/api/v1/auth/keys'
        && $request['code'] === 'or-code'
        && $request['code_verifier'] === $pending->code_verifier
        && $request['code_challenge_method'] === 'S256');

    $credential = AiProviderCredential::sole();
    expect($credential->apiKey())->toBe('sk-or-v1-connectedkey1234')
        ->and($credential->is_default)->toBeTrue()
        ->and($credential->created_by)->toBe($this->owner->id)
        ->and(AiProviderConnectState::count())->toBe(0);
});

it('makes a fresh connection the default over an older key for the same provider', function () {
    Http::fake([
        'openrouter.ai/api/v1/auth/keys' => Http::response(['key' => 'sk-or-v1-newkey5678']),
        'openrouter.ai/api/v1/key' => Http::response(['data' => []]),
    ]);
    $old = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('openrouter')->default()->create();
    Passport::actingAs($this->owner);

    completeConnect(startConnect($this->workspace, 'openrouter')['query']['state'])->assertCreated();

    expect($old->fresh()->is_default)->toBeFalse()
        ->and(AiProviderCredential::query()->where('is_default', true)->sole()->apiKey())->toBe('sk-or-v1-newkey5678');
});

it('adds a personal key for a member who cannot manage the workspace keys', function () {
    Http::fake([
        'openrouter.ai/api/v1/auth/keys' => Http::response(['key' => 'sk-or-v1-personal9999']),
        'openrouter.ai/api/v1/key' => Http::response(['data' => []]),
    ]);
    $member = connectMember($this->workspace, Role::Member);
    Passport::actingAs($member);

    $started = startConnect($this->workspace, 'openrouter');
    completeConnect($started['query']['state'])->assertCreated();

    expect($started['scope'])->toBe('personal')
        ->and(AiProviderCredential::sole())
        ->scope->toBe(ConnectorCredentialScope::Personal)
        ->created_by->toBe($member->id);
});

it('refuses a viewer, who may add no keys at all', function () {
    Passport::actingAs(connectMember($this->workspace, Role::Viewer));

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/openrouter/connect")
        ->assertUnprocessable()
        ->assertJsonPath('message', "You can't add AI provider keys in this workspace. Ask an admin.");

    expect(AiProviderConnectState::count())->toBe(0);
});

it('refuses to finish when the member may no longer add the key', function () {
    Http::fake();
    $member = connectMember($this->workspace, Role::Member);
    Passport::actingAs($member);
    $state = startConnect($this->workspace, 'openrouter')['query']['state'];

    // An admin turns personal keys off while the member is at OpenRouter.
    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $this->workspace->id, 'allow_personal_keys' => false]);

    completeConnect($state)->assertUnprocessable();

    Http::assertNothingSent();
    expect(AiProviderCredential::count())->toBe(0);
});

it('completes each sign-in at most once and never after it expires', function () {
    Http::fake([
        'openrouter.ai/api/v1/auth/keys' => Http::response(['key' => 'sk-or-v1-onceonly0000']),
        'openrouter.ai/api/v1/key' => Http::response(['data' => []]),
    ]);
    Passport::actingAs($this->owner);
    $state = startConnect($this->workspace, 'openrouter')['query']['state'];

    completeConnect($state)->assertCreated();
    completeConnect($state)->assertUnprocessable()
        ->assertJsonPath('message', 'This sign-in link has expired or was already used. Start the connection again.');

    $expired = startConnect($this->workspace, 'openrouter')['query']['state'];
    $this->travel(11)->minutes();
    completeConnect($expired)->assertUnprocessable();
    completeConnect('not-a-real-state')->assertUnprocessable();

    expect(AiProviderCredential::count())->toBe(1);
});

it('stores nothing when the provider refuses the code', function () {
    Http::fake(['openrouter.ai/api/v1/auth/keys' => Http::response(['error' => 'Invalid code or code_verifier'], 403)]);
    Passport::actingAs($this->owner);

    completeConnect(startConnect($this->workspace, 'openrouter')['query']['state'])
        ->assertUnprocessable()
        ->assertJsonPath('message', "OpenRouter didn't complete the connection (HTTP 403). Try connecting again.");

    expect(AiProviderCredential::count())->toBe(0);
});

it('offers no one-click connect for a provider without one, or one switched off', function () {
    config(['byok.providers.openrouter.connect.enabled' => false]);
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/openrouter/connect")->assertUnprocessable();
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/anthropic/connect")->assertUnprocessable();
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/not-a-provider/connect")->assertNotFound();
});

it('connects Hugging Face with OAuth and keeps the refresh token for renewals', function () {
    Http::fake([
        'huggingface.co/oauth/token' => Http::response([
            'access_token' => 'hf_oauth_accesstoken1234',
            'refresh_token' => 'hf_refresh_token',
            'expires_in' => 28800,
            'token_type' => 'Bearer',
        ]),
        'huggingface.co/api/whoami-v2' => Http::response(['name' => 'jay']),
    ]);
    Passport::actingAs($this->owner);

    $started = startConnect($this->workspace, 'huggingface');

    expect($started['authorize_url'])->toStartWith('https://huggingface.co/oauth/authorize?')
        ->and($started['query'])->toMatchArray([
            'response_type' => 'code',
            'client_id' => 'hf-client-id',
            'redirect_uri' => 'https://app.example.test/oauth/ai-provider-complete',
            'scope' => 'openid profile inference-api',
            'code_challenge_method' => 'S256',
        ]);

    $verifier = AiProviderConnectState::sole()->code_verifier;
    completeConnect($started['query']['state'], 'hf-code')->assertCreated()
        ->assertJsonPath('data.ai_provider_credential.auth_method', 'oauth');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://huggingface.co/oauth/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'hf-code'
        && $request['code_verifier'] === $verifier
        && $request['client_id'] === 'hf-client-id'
        && ! isset($request['client_secret']));

    $credential = AiProviderCredential::sole();
    expect($credential->auth_method)->toBe(AiProviderAuthMethod::OAuth)
        ->and($credential->apiKey())->toBe('hf_oauth_accesstoken1234')
        ->and($credential->refreshToken())->toBe('hf_refresh_token')
        ->and($credential->validation_status)->toBe(AiProviderCredentialStatus::Valid)
        ->and($credential->token_expires_at->between(now()->addHours(7), now()->addHours(9)))->toBeTrue();
});

it('turns a one-click connection into a plain key when a key is pasted over it', function () {
    Http::fake(['huggingface.co/*' => Http::response(['name' => 'jay'])]);
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')->oauth()->create();
    Passport::actingAs($this->owner);

    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/ai-provider-credentials/{$credential->id}", ['api_key' => 'hf_pastedtoken0000'])
        ->assertOk()
        ->assertJsonPath('data.ai_provider_credential.auth_method', 'api_key');

    expect($credential->fresh())
        ->auth_method->toBe(AiProviderAuthMethod::ApiKey)
        ->token_expires_at->toBeNull()
        ->and($credential->fresh()->refreshToken())->toBeNull();
});

it('refuses to finish a sign-in started by someone else, and burns it', function () {
    Http::fake();
    Passport::actingAs($this->owner);
    $state = startConnect($this->workspace, 'openrouter')['query']['state'];

    // The sign-in link reaches another person, who approves it at OpenRouter.
    $victim = User::factory()->create();
    app(WorkspaceService::class)->create($victim, ['name' => 'Victim Co']);
    Passport::actingAs($victim);

    completeConnect($state, 'victim-code')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This sign-in was started from a different account. Sign in as that account, or start the connection again.');

    Http::assertNothingSent();
    expect(AiProviderCredential::count())->toBe(0)
        ->and(AiProviderConnectState::count())->toBe(0);

    // Burnt: not even the member who started it can finish it now.
    Passport::actingAs($this->owner);
    completeConnect($state)->assertUnprocessable();
});

it('requires a signed-in member to finish a sign-in', function () {
    Passport::actingAs($this->owner);
    $state = startConnect($this->workspace, 'openrouter')['query']['state'];
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/ai-providers/connect/complete', ['state' => $state, 'code' => 'auth-code'])->assertUnauthorized();

    expect(AiProviderConnectState::count())->toBe(1);
});
