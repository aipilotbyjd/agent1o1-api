<?php

use App\Models\Auth\ApiKey;
use App\Models\Connectors\Connector;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workspace, 1: string}
 */
function connectorApiKey(array $abilities = ['connectors:manage']): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Provisioning key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => $abilities,
    ]);

    return [$workspace, $plainTextKey];
}

it('provisions and lists a connector credential without ever returning its data', function () {
    [$workspace, $key] = connectorApiKey();
    $connector = Connector::factory()->create(['name' => 'Acme CRM']);

    $created = $this->withToken($key)->postJson('/api/public/v1/connector-credentials', [
        'connector_id' => $connector->id,
        'name' => 'Production CRM',
        'data' => ['api_key' => 'sk_live_supersecret'],
    ]);

    $created->assertCreated()->assertJsonPath('data.connector_credential.name', 'Production CRM');
    expect($created->json('data.connector_credential'))->not->toHaveKey('data');
    expect($created->json('data.connector_credential.created_by'))->toBeNull();

    $index = $this->withToken($key)->getJson('/api/public/v1/connector-credentials');
    $index->assertOk();
    expect($index->json('data.connector_credentials'))->toHaveCount(1);
    expect(json_encode($index->json()))->not->toContain('sk_live_supersecret');

    // Stored encrypted, and still usable server-side.
    expect($workspace->connectorCredentials()->sole()->data)->toBe(['api_key' => 'sk_live_supersecret']);
});

it('refuses to hand-write credentials for an oauth connector', function () {
    [$workspace, $key] = connectorApiKey();
    $connector = Connector::factory()->oauth()->create();

    $this->withToken($key)->postJson('/api/public/v1/connector-credentials', [
        'connector_id' => $connector->id,
        'name' => 'Sneaky',
        'data' => ['access_token' => 'pasted-by-hand'],
    ])->assertStatus(422);
});

it('lists the connector catalog and deletes a credential', function () {
    [$workspace, $key] = connectorApiKey();
    $connector = Connector::factory()->create();
    Connector::factory()->create(['is_active' => false]);

    $catalog = $this->withToken($key)->getJson('/api/public/v1/connectors');
    $catalog->assertOk();
    expect($catalog->json('data.connectors'))->toHaveCount(1);

    $credential = $workspace->connectorCredentials()->create([
        'connector_id' => $connector->id,
        'name' => 'Temp',
        'data' => ['api_key' => 'x'],
    ]);

    $this->withToken($key)->deleteJson("/api/public/v1/connector-credentials/{$credential->id}")->assertNoContent();
    expect($workspace->connectorCredentials()->count())->toBe(0);
});

it('refuses connector management to a key without the ability', function () {
    [$workspace, $key] = connectorApiKey(['workflows:read']);

    $this->withToken($key)->getJson('/api/public/v1/connector-credentials')->assertForbidden();
});

it('404s a credential belonging to another workspace', function () {
    [$mine, $key] = connectorApiKey();
    [$theirs] = connectorApiKey();

    $connector = Connector::factory()->create();
    $foreign = $theirs->connectorCredentials()->create([
        'connector_id' => $connector->id,
        'name' => 'Theirs',
        'data' => ['api_key' => 'x'],
    ]);

    $this->withToken($key)->getJson("/api/public/v1/connector-credentials/{$foreign->id}")->assertNotFound();
    $this->withToken($key)->deleteJson("/api/public/v1/connector-credentials/{$foreign->id}")->assertNotFound();
});
