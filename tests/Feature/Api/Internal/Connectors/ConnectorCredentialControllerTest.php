<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Workspaces\Role;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use Laravel\Passport\Passport;

it('lists the connector catalog', function () {
    Connector::factory()->create(['key' => 'slack', 'name' => 'Slack']);
    [, $owner] = ownerWorkspaceForConnectors();

    Passport::actingAs($owner);

    $response = $this->getJson('/api/v1/connectors');

    $response->assertOk();
    expect(collect($response->json('data.connectors'))->pluck('key'))->toContain('slack');
});

it('flags oauth connectors the server has no client credentials for', function () {
    Connector::factory()->create(['key' => 'notion']);
    Connector::factory()->oauth()->create(['key' => 'github']);
    Connector::factory()->oauth()->create(['key' => 'gmail']);
    [, $owner] = ownerWorkspaceForConnectors();

    config([
        'services.github.client_id' => 'client-123',
        'services.github.client_secret' => 'secret-123',
        'services.gmail' => null,
    ]);

    Passport::actingAs($owner);

    $configured = collect($this->getJson('/api/v1/connectors')->assertOk()->json('data.connectors'))
        ->pluck('is_configured', 'key');

    expect($configured->all())->toMatchArray([
        'notion' => true,
        'github' => true,
        'gmail' => false,
    ]);
});

it('creates a manual connector credential and never exposes its data', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->create(['auth_type' => ConnectorAuthType::ApiKey]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", [
        'connector_id' => $connector->id,
        'name' => 'My API Key',
        'data' => ['api_key' => 'sk_test_secret'],
    ]);

    $response->assertCreated();
    expect($response->json('data.connector_credential.name'))->toBe('My API Key');
    expect($response->json('data.connector_credential'))->not->toHaveKey('data');
    $response->assertJsonMissing(['sk_test_secret']);

    $credential = ConnectorCredential::query()->firstWhere('name', 'My API Key');
    expect($credential->data)->toBe(['api_key' => 'sk_test_secret']);
});

it('rejects storing credential data directly for an oauth-only connector', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->oauth()->create();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", [
        'connector_id' => $connector->id,
        'name' => 'Should fail',
        'data' => ['access_token' => 'abc'],
    ]);

    $response->assertStatus(422);
    expect(ConnectorCredential::query()->where('name', 'Should fail')->exists())->toBeFalse();
});

it('does not leak another workspace connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    [$otherWorkspace] = ownerWorkspaceForConnectors();
    $foreign = ConnectorCredential::factory()->forWorkspace($otherWorkspace)->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$foreign->id}")->assertNotFound();
});

it('updates and deletes a connector credential', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->create(['name' => 'Old name']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}", ['name' => 'New name'])
        ->assertOk()
        ->assertJsonPath('data.connector_credential.name', 'New name');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$credential->id}")->assertNoContent();
    expect(ConnectorCredential::find($credential->id))->toBeNull();
});

it('hides another member\'s personal credential entirely', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $colleague = User::factory()->create();
    $workspace->members()->create(['user_id' => $colleague->id, 'role' => Role::Editor, 'joined_at' => now()]);

    $personal = ConnectorCredential::factory()->forWorkspace($workspace)
        ->create(['created_by' => $colleague->id, 'scope' => 'personal']);
    $team = ConnectorCredential::factory()->forWorkspace($workspace)->create(['name' => 'Shared']);

    Passport::actingAs($owner);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials")->assertOk();
    expect(collect($index->json('data.connector_credentials'))->pluck('id'))
        ->toContain($team->id)
        ->not->toContain($personal->id);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$personal->id}")->assertNotFound();
    $this->patchJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$personal->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$personal->id}")->assertNotFound();
});

it('lets the creator see and use their own personal credential', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $personal = ConnectorCredential::factory()->forWorkspace($workspace)
        ->create(['created_by' => $owner->id, 'scope' => 'personal', 'name' => 'My own']);

    Passport::actingAs($owner);

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials")->assertOk();
    expect(collect($index->json('data.connector_credentials'))->pluck('id'))->toContain($personal->id);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$personal->id}")->assertOk();
});

it('creates a personal connector credential owned by the creator', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->create(['auth_type' => ConnectorAuthType::ApiKey]);

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials", [
        'connector_id' => $connector->id,
        'name' => 'My personal key',
        'data' => ['api_key' => 'sk_test_personal'],
        'scope' => 'personal',
    ]);

    $response->assertCreated();
    expect($response->json('data.connector_credential.scope'))->toBe('personal');
    expect(ConnectorCredential::query()->firstWhere('name', 'My personal key')->created_by)->toBe($owner->id);
});

it('sets and swaps the default credential for a connector', function () {
    [$workspace, $owner] = ownerWorkspaceForConnectors();
    $connector = Connector::factory()->create();
    $first = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create();
    $second = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$first->id}/default")
        ->assertOk()
        ->assertJsonPath('data.connector_credential.is_default', true);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/connector-credentials/{$second->id}/default")
        ->assertOk()
        ->assertJsonPath('data.connector_credential.is_default', true);

    expect($first->fresh()->is_default)->toBeFalse();
    expect($second->fresh()->is_default)->toBeTrue();
});
