<?php

use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('rejects a request without an api key', function () {
    $this->getJson('/api/public/v1/me')->assertUnauthorized();
});

it('rejects an invalid api key', function () {
    $this->withToken('not-a-real-key')
        ->getJson('/api/public/v1/me')
        ->assertUnauthorized();
});

it('resolves a valid api key to its workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['*'],
    ]);

    $this->withToken($plainTextKey)
        ->getJson('/api/public/v1/me')
        ->assertOk()
        ->assertJsonPath('workspace.id', $workspace->id);
});

it('rejects an expired api key', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Expired key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['*'],
        'expires_at' => now()->subDay(),
    ]);

    $this->withToken($plainTextKey)
        ->getJson('/api/public/v1/me')
        ->assertUnauthorized();
});

it('rejects an api key whose workspace is suspended', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->forceFill(['suspended_at' => now()])->save();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $apiKey = $workspace->apiKeys()->create([
        'name' => 'CI key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['*'],
    ]);

    $this->withToken($plainTextKey)
        ->getJson('/api/public/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'This workspace has been suspended.');

    expect($apiKey->fresh()->last_used_at)->toBeNull();
});

it('issues keys with a recognisable prefix and a valid checksum', function () {
    $plainTextKey = ApiKey::generatePlainTextKey();

    expect($plainTextKey)->toStartWith('a1o1_live_')
        ->toMatch(ApiKey::PATTERN)
        ->and(ApiKey::hasValidChecksum($plainTextKey))->toBeTrue()
        ->and(ApiKey::generatePlainTextKey())->not->toBe($plainTextKey);
});

it('rejects a key in the current format whose checksum was tampered with', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $lastCharacter = substr($plainTextKey, -1);
    $tamperedKey = substr($plainTextKey, 0, -1).($lastCharacter === 'a' ? 'b' : 'a');

    // Even a stored row for the tampered key is never reached.
    $workspace->apiKeys()->create([
        'name' => 'Tampered key',
        'hashed_key' => ApiKey::hash($tamperedKey),
        'abilities' => ['*'],
    ]);

    expect(ApiKey::hasValidChecksum($tamperedKey))->toBeFalse();

    $this->withToken($tamperedKey)
        ->getJson('/api/public/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid API key.');
});

it('still accepts keys issued in the older ak_ format', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $legacyKey = 'ak_'.str_repeat('x', 40);
    $workspace->apiKeys()->create([
        'name' => 'Legacy key',
        'hashed_key' => ApiKey::hash($legacyKey),
        'abilities' => ['*'],
    ]);

    $this->withToken($legacyKey)
        ->getJson('/api/public/v1/me')
        ->assertOk()
        ->assertJsonPath('workspace.id', $workspace->id);
});
