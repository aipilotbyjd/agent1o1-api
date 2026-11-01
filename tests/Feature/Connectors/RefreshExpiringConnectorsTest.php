<?php

use App\Jobs\Connectors\RefreshConnectorCredentialJob;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Services\Connectors\OAuthConnectorFlowService;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

it('queues refreshes only for tokens about to expire that can be refreshed', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create(['key' => 'gmail_test']);
    $make = fn (array $attributes) => ConnectorCredential::factory()->forWorkspace($workspace)->forConnector($connector)->create($attributes);

    $soon = $make(['data' => ['access_token' => 'a', 'refresh_token' => 'r'], 'expires_at' => now()->addMinutes(5)]);
    $make(['data' => ['access_token' => 'b'], 'expires_at' => now()->addMinutes(5)]);
    $make(['data' => ['access_token' => 'c', 'refresh_token' => 'r'], 'expires_at' => now()->addHour()]);
    $make(['data' => ['access_token' => 'd', 'refresh_token' => 'r'], 'expires_at' => now()->subMinute()]);

    $this->artisan('connectors:refresh-expiring')->assertSuccessful();

    Queue::assertPushed(RefreshConnectorCredentialJob::class, 1);
    Queue::assertPushed(RefreshConnectorCredentialJob::class, fn ($job) => $job->credential->is($soon));
});

it('asks Google for offline access so connections can be refreshed', function () {
    config(['services.google_test.client_id' => 'cid', 'services.google_test.client_secret' => 'secret']);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $connector = Connector::factory()->create([
        'key' => 'google_test',
        'auth_type' => 'oauth2',
        'oauth' => ['authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token_url' => 'https://oauth2.googleapis.com/token', 'scopes' => ['x'], 'authorize_params' => ['access_type' => 'offline', 'prompt' => 'consent']],
    ]);

    $url = app(OAuthConnectorFlowService::class)->initiate($workspace, $owner, $connector, 'Mine', 'https://app.test/callback')['authorize_url'];

    expect($url)->toContain('access_type=offline')->toContain('prompt=consent');
});
