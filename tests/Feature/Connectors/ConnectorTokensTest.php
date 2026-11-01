<?php

use App\Exceptions\ConnectorException;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\User;
use App\Nodes\Integrations\Gmail\GmailListMessagesNode;
use App\Services\Connectors\ConnectorTokens;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->connector = Connector::query()->where('key', 'gmail')->first() ?? Connector::factory()->oauth()->create(['key' => 'gmail']);
    $this->connector->forceFill(['auth_type' => 'oauth2', 'oauth' => ['authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token_url' => 'https://oauth2.googleapis.com/token', 'scopes' => []]])->save();
    config(['services.gmail.client_id' => 'cid', 'services.gmail.client_secret' => 'secret']);

    $this->credential = fn (array $attributes = []) => ConnectorCredential::factory()->forWorkspace($this->workspace)->forConnector($this->connector)->create([
        'data' => ['access_token' => 'old-token', 'refresh_token' => 'refresh-1'],
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);
    $this->tokens = app(ConnectorTokens::class);
});

it('uses a token that is still valid without refreshing', function () {
    Http::fake();

    expect($this->tokens->accessToken(($this->credential)()))->toBe('old-token');
    Http::assertNothingSent();
});

it('refreshes a token that has expired or is about to, keeping the refresh token', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600])]);
    $credential = ($this->credential)(['expires_at' => now()->subMinutes(30)]);

    expect($this->tokens->accessToken($credential))->toBe('new-token')
        ->and($credential->fresh())
        ->data->toMatchArray(['access_token' => 'new-token', 'refresh_token' => 'refresh-1'])
        ->isExpired()->toBeFalse();
});

it('asks for a reconnect when an expired token cannot be refreshed', function () {
    $credential = ($this->credential)(['data' => ['access_token' => 'old-token'], 'expires_at' => now()->subMinute()]);

    expect($credential->isUsable())->toBeFalse()
        ->and(fn () => $this->tokens->accessToken($credential))->toThrow(ConnectorException::class, 'Reconnect it in Apps');
});

it('stops refreshing once the provider rejects the refresh token', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    $credential = ($this->credential)(['expires_at' => now()->subMinute()]);

    expect($credential->isUsable())->toBeTrue()
        ->and(fn () => $this->tokens->accessToken($credential))->toThrow(ConnectorException::class, 'Reconnect it in Apps')
        ->and($credential->fresh()->isUsable())->toBeFalse();

    Http::assertSentCount(1);
});

it('keeps a still-valid token when the provider cannot be reached', function () {
    Http::fake(['oauth2.googleapis.com/token' => fn () => throw new ConnectionException('timeout')]);

    expect($this->tokens->accessToken(($this->credential)(['expires_at' => now()->addSeconds(30)])))->toBe('old-token');
});

it('gives workflow nodes a refreshed token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::response(['messages' => []]),
    ]);
    $credential = ($this->credential)(['expires_at' => now()->subHour()]);
    $run = new Run;
    $run->forceFill(['workspace_id' => $this->workspace->id]);

    app(GmailListMessagesNode::class)->execute($run, ['credential_id' => $credential->id], []);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'gmail.googleapis.com') && $request->hasHeader('Authorization', 'Bearer new-token'));
});
