<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Connectors\OAuthConnectorState;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForOAuth(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('initiates the oauth flow with a state-backed authorize url', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);

    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/oauth/initiate", [
        'connector_id' => $connector->id,
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
    ]);

    $response->assertOk();
    expect($response->json('data.authorize_url'))->toStartWith($connector->oauth['authorize_url']);
    expect($response->json('data.authorize_url'))->toContain('client_id=client-123');
    expect(OAuthConnectorState::where('state', $response->json('data.state'))->exists())->toBeTrue();
});

it('exchanges the callback code for tokens and stores a connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);

    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    Http::fake([
        $connector->oauth['token_url'] => Http::response([
            'access_token' => 'gh-access-token',
            'refresh_token' => 'gh-refresh-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ]),
    ]);

    $state = OAuthConnectorState::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'connector_id' => $connector->id,
        'state' => 'test-state-token',
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => $state->state,
        'code' => 'auth-code-123',
    ]));

    $response->assertCreated();
    Http::assertSent(fn ($request) => $request->hasHeader('Accept', 'application/json')
        && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
        && $request['code'] === 'auth-code-123');
    expect($response->json('data.connector_credential'))->not->toHaveKey('data');

    $credential = ConnectorCredential::query()->where('workspace_id', $workspace->id)->firstOrFail();
    expect($credential->name)->toBe('My GitHub');
    expect($credential->data['access_token'])->toBe('gh-access-token');
    expect($credential->data['refresh_token'])->toBe('gh-refresh-token');
    expect($credential->expires_at)->not->toBeNull();

    expect(OAuthConnectorState::where('state', 'test-state-token')->exists())->toBeFalse();
});

it('rejects an unknown or expired oauth state on callback', function () {
    $response = $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => 'does-not-exist',
        'code' => 'auth-code-123',
    ]));

    $response->assertStatus(422);
});

it('rejects storing a manual credential for an oauth-only connector at the schema level', function () {
    $connector = Connector::factory()->oauth()->create();

    expect($connector->auth_type)->toBe(ConnectorAuthType::OAuth2);
    expect($connector->isOAuth())->toBeTrue();
});

it('rejects invalid token responses without saving a credential', function (mixed $body) {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);
    Http::fake([$connector->oauth['token_url'] => Http::response($body)]);

    $state = OAuthConnectorState::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'connector_id' => $connector->id,
        'state' => 'invalid-token-state',
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => $state->state,
        'code' => 'auth-code',
    ]))->assertUnprocessable();

    expect(ConnectorCredential::where('workspace_id', $workspace->id)->exists())->toBeFalse();
})->with([
    'provider error' => [['error' => 'bad_verification_code']],
    'missing token' => [['token_type' => 'bearer']],
    'empty token' => [['access_token' => '']],
    'whitespace token' => [['access_token' => '  ']],
    'non-string token' => [['access_token' => 123]],
    'non-json response' => ['access_token=example'],
]);

it('refuses to initiate the oauth flow for a connector the server has no client credentials for', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'gmail', 'name' => 'Gmail']);

    config(['services.gmail' => null]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/oauth/initiate", [
        'connector_id' => $connector->id,
        'name' => 'My Gmail',
        'redirect_uri' => 'https://app.test/callback',
    ]);

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('Gmail');
    expect(OAuthConnectorState::query()->exists())->toBeFalse();
});

it('fails the callback cleanly when the connector lost its client credentials', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'gmail', 'name' => 'Gmail']);

    config(['services.gmail' => null]);

    $state = OAuthConnectorState::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'connector_id' => $connector->id,
        'state' => 'stale-state-token',
        'name' => 'My Gmail',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => $state->state,
        'code' => 'auth-code-123',
    ]))->assertUnprocessable();

    expect(ConnectorCredential::query()->exists())->toBeFalse();
});

it('keeps the provider\'s error body out of the callback response and refuses a replayed state', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);

    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);
    Http::fake([$connector->oauth['token_url'] => Http::response(['error_description' => 'client secret secret-123 rejected'], 400)]);

    OAuthConnectorState::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'connector_id' => $connector->id,
        'state' => 'replayed-state',
        'name' => 'My GitHub',
        'redirect_uri' => 'https://app.test/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $query = '/api/oauth/connectors/callback?'.http_build_query(['state' => 'replayed-state', 'code' => 'auth-code-123']);

    $first = $this->getJson($query)->assertUnprocessable();
    expect($first->getContent())->not->toContain('secret-123');

    $this->getJson($query)->assertUnprocessable();
    Http::assertSentCount(1);
});

it('reconnects an existing account in place instead of creating a second one', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create([
        'name' => 'Work GitHub',
        'created_by' => $owner->id,
        'data' => ['access_token' => 'dead-token', 'refresh_token' => 'dead-refresh', 'refresh_rejected_at' => now()->toIso8601String()],
        'expires_at' => now()->subDay(),
    ]);

    Passport::actingAs($owner);

    $initiate = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/oauth/initiate", [
        'connector_id' => $connector->id,
        'name' => 'Ignored for a reconnect',
        'redirect_uri' => 'https://app.test/callback',
        'credential_id' => $credential->id,
    ])->assertOk();

    Http::fake([$connector->oauth['token_url'] => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'fresh-refresh', 'expires_in' => 3600])]);

    $this->getJson('/api/oauth/connectors/callback?'.http_build_query([
        'state' => $initiate->json('data.state'),
        'code' => 'auth-code-123',
    ]))->assertCreated()->assertJsonPath('data.connector_credential.id', $credential->id);

    $credential->refresh();
    expect(ConnectorCredential::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($credential->name)->toBe('Work GitHub')
        ->and($credential->data)->toBe(['access_token' => 'fresh-token', 'refresh_token' => 'fresh-refresh'])
        ->and($credential->isUsable())->toBeTrue();
});

it('refuses to reconnect a credential from another workspace or connector', function () {
    [$workspace, $owner] = ownerWorkspaceForOAuth();
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    $otherConnector = Connector::factory()->oauth()->create(['key' => 'slack']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    [$otherWorkspace] = ownerWorkspaceForOAuth();
    $foreign = ConnectorCredential::factory()->forWorkspace($otherWorkspace)->forConnector($connector)->create();
    $wrongConnector = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($otherConnector)->create(['created_by' => $owner->id]);

    Passport::actingAs($owner);

    foreach ([$foreign, $wrongConnector] as $credential) {
        $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/oauth/initiate", [
            'connector_id' => $connector->id,
            'name' => 'Nope',
            'redirect_uri' => 'https://app.test/callback',
            'credential_id' => $credential->id,
        ])->assertNotFound();
    }

    expect(OAuthConnectorState::query()->count())->toBe(0);
});
