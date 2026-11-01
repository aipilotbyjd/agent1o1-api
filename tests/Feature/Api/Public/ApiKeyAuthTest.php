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
