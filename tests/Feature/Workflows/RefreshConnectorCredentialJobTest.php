<?php

use App\Exceptions\ConnectorException;
use App\Jobs\Connectors\RefreshConnectorCredentialJob;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Notifications\Connectors\ConnectorCredentialExpiredNotification;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('refreshes an oauth connector credential', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);

    $credential = ConnectorCredential::factory()
        ->forWorkspace($workspace)
        ->forConnector($connector)
        ->create(['data' => ['access_token' => 'stale', 'refresh_token' => 'refresh-abc']]);

    Http::fake([
        $connector->oauth['token_url'] => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
    ]);

    (new RefreshConnectorCredentialJob($credential))->handle(app(ConnectorTokens::class));

    Http::assertSent(fn ($request) => $request->hasHeader('Accept', 'application/json'));

    expect($credential->fresh()->data['access_token'])->toBe('fresh');
    expect($credential->fresh()->data['refresh_token'])->toBe('refresh-abc');
});

it('marks the credential expired and notifies workspace admins on final refresh failure', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->oauth()->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create();

    (new RefreshConnectorCredentialJob($credential))->failed(new RuntimeException('refresh failed'));

    expect($credential->fresh()->isExpired())->toBeTrue();
    Notification::assertSentTo($owner, ConnectorCredentialExpiredNotification::class);
});

it('preserves existing tokens when refresh returns an oauth error', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->oauth()->create(['key' => 'github']);
    config(['services.github.client_id' => 'client-123', 'services.github.client_secret' => 'secret-123']);
    $tokens = ['access_token' => 'existing', 'refresh_token' => 'refresh-abc'];
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)
        ->create(['data' => $tokens]);
    Http::fake([$connector->oauth['token_url'] => Http::response(['error' => 'invalid_grant'])]);

    expect(fn () => app(OAuthConnectorFlowService::class)->refresh($credential))
        ->toThrow(ConnectorException::class);
    expect($credential->fresh()->data)->toBe($tokens);
});
